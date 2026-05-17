<?php

namespace App\Http\Controllers\Api\Editorial;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EditorialDecision;
use App\Models\Notification;
use App\Models\Paper;
use App\Models\Review;
use App\Models\ReviewerProfile;
use App\Models\ReviewRound;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * Get editorial dashboard stats
     */
    public function dashboard(Request $request)
    {
        $editorialId = $request->user()->id;

        $stats = [
            'assigned_papers' => Paper::where('editorial_assigned', $editorialId)->count(),
            'pending_review' => Paper::where('editorial_assigned', $editorialId)
                ->whereIn('status', ['submitted', 'under_review'])->count(),
            'revision_requested' => Paper::where('editorial_assigned', $editorialId)
                ->where('status', 'revision_required')->count(),
            'accepted' => Paper::where('editorial_assigned', $editorialId)
                ->where('status', 'accepted')->count(),
            'published' => Paper::where('editorial_assigned', $editorialId)
                ->where('is_published', true)->count(),
            'unpublished' => Paper::where('editorial_assigned', $editorialId)
                ->where('status', 'accepted')
                ->where('is_published', false)->count(),
            'rejected' => Paper::where('editorial_assigned', $editorialId)
                ->where('status', 'rejected')->count(),
            'completed_today' => Paper::where('editorial_assigned', $editorialId)
                ->whereIn('status', ['accepted', 'rejected'])
                ->whereDate('decision_date', today())->count(),
            'active_reviews' => \App\Models\Review::whereHas('reviewRound', function ($q) use ($editorialId) {
                $q->whereHas('paper', function ($q) use ($editorialId) {
                    $q->where('editorial_assigned', $editorialId);
                });
            })->where('status', 'in_progress')->count(),
            'pending_actions' => Paper::where('editorial_assigned', $editorialId)
                ->whereIn('status', ['submitted', 'under_review', 'revision_required'])->count(),
        ];

        return response()->json([
            'message' => 'Dashboard stats retrieved',
            'stats' => $stats,
        ]);
    }

    /**
     * List assigned papers
     */
    public function papers(Request $request)
    {
        $papers = Paper::where('editorial_assigned', $request->user()->id)
            ->with(['submitter', 'category', 'files', 'currentReviewRound.reviews.reviewer'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->publish, function ($q) use ($request) {
                if ($request->publish === 'published') {
                    $q->where('is_published', true);
                } elseif ($request->publish === 'unpublished') {
                    $q->where('status', 'accepted')->where('is_published', false);
                }
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%");
            })
            ->latest('submission_date')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'message' => 'Papers retrieved',
            'papers' => $papers,
        ]);
    }

    /**
     * Show paper details for editorial
     */
    public function showPaper(Request $request, $id)
    {
        $paper = Paper::where('editorial_assigned', $request->user()->id)
            ->with([
                'submitter',
                'authors.user',
                'category',
                'files',
                'reviewRounds' => function ($q) {
                    $q->with([
                        'reviews' => function ($q) {
                            $q->with('reviewer');
                        },
                        'editorialDecision.decidedBy'
                    ])->orderBy('round_number', 'desc');
                }
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Paper details retrieved',
            'paper' => $paper,
        ]);
    }

    /**
     * Start new review round
     */
    public function startReviewRound(Request $request, $paperId)
    {
        $paper = Paper::where('editorial_assigned', $request->user()->id)
            ->findOrFail($paperId);

        if (!in_array($paper->status, ['submitted', 'revision_required'])) {
            return response()->json([
                'message' => 'Paper is not in a reviewable state. Current status: ' . $paper->status,
            ], 422);
        }

        $roundNumber = $paper->current_round + 1;

        $reviewRound = ReviewRound::create([
            'paper_id' => $paper->id,
            'round_number' => $roundNumber,
            'status' => 'in_progress',
            'started_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        $paper->update([
            'current_round' => $roundNumber,
            'status' => 'under_review',
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'review_round_started',
            'description' => "Review round {$roundNumber} started",
        ]);

        return response()->json([
            'message' => 'Review round started',
            'review_round' => $reviewRound->load('paper'),
        ]);
    }

    /**
     * Get available reviewers
     */
    public function availableReviewers(Request $request)
    {
        $reviewers = User::role('reviewer')
            ->where('status', 'active')
            ->whereHas('reviewerProfile', function ($q) {
                $q->where('availability_status', 'available')
                    ->whereRaw('current_reviews < max_reviews');
            })
            ->with('reviewerProfile')
            ->get()
            ->map(function ($reviewer) {
                return [
                    'id' => $reviewer->id,
                    'name' => $reviewer->full_name,
                    'email' => $reviewer->email,
                    'expertise' => $reviewer->reviewerProfile->expertise_keywords ?? [],
                    'current_reviews' => $reviewer->reviewerProfile->current_reviews ?? 0,
                    'max_reviews' => $reviewer->reviewerProfile->max_reviews ?? 5,
                    'total_reviews' => $reviewer->reviewerProfile->total_reviews_completed ?? 0,
                ];
            });

        return response()->json([
            'message' => 'Available reviewers retrieved',
            'reviewers' => $reviewers,
        ]);
    }

    /**
     * Assign reviewers to round
     */
    public function assignReviewers(Request $request, $roundId)
    {
        $request->validate([
            'reviewer_ids' => 'required|array|min:1',
            'reviewer_ids.*' => 'required|exists:users,id',
        ]);

        $reviewRound = ReviewRound::findOrFail($roundId);

        $paper = Paper::where('editorial_assigned', $request->user()->id)
            ->where('id', $reviewRound->paper_id)
            ->first();

        if (!$paper) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        $assignedReviewers = [];
        $dueDate = $request->due_date ?? now()->addDays(21);

        foreach ($request->reviewer_ids as $reviewerId) {
            $exists = Review::where('review_round_id', $roundId)
                ->where('reviewer_id', $reviewerId)
                ->exists();

            if ($exists) continue;

            $isAuthor = $paper->authors()->where('user_id', $reviewerId)->exists();
            if ($isAuthor) continue;

            $review = Review::create([
                'review_round_id' => $roundId,
                'reviewer_id' => $reviewerId,
                'status' => 'pending',
                'assigned_at' => now(),
                'due_date' => $dueDate,
            ]);

            $reviewerProfile = ReviewerProfile::where('user_id', $reviewerId)->first();
            if ($reviewerProfile) {
                $reviewerProfile->increment('current_reviews');
                if ($reviewerProfile->fresh()->current_reviews >= $reviewerProfile->max_reviews) {
                    $reviewerProfile->update(['availability_status' => 'busy']);
                }
            }

            Notification::create([
                'user_id' => $reviewerId,
                'type' => 'review_invitation',
                'title' => 'Review Invitation',
                'message' => "You have been invited to review paper '{$paper->title}'",
                'data' => json_encode([
                    'review_id' => $review->id,
                    'paper_id' => $paper->id,
                    'due_date' => $dueDate->toDateString(),
                ]),
            ]);

            $assignedReviewers[] = $review->load('reviewer');
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'reviewers_assigned',
            'description' => count($assignedReviewers) . ' reviewer(s) assigned',
        ]);

        return response()->json([
            'message' => 'Reviewers assigned successfully',
            'assigned_reviewers' => $assignedReviewers,
        ]);
    }

    /**
     * Make editorial decision
     */
    public function makeDecision(Request $request, $roundId)
    {
        $request->validate([
            'decision' => ['required', Rule::in(['accept', 'minor_revision', 'major_revision', 'reject'])],
            'comments' => 'required|string|min:10',
            'revision_deadline' => 'nullable|date|after:today',
        ]);

        $reviewRound = ReviewRound::with(['reviews', 'paper'])->findOrFail($roundId);

        $paper = Paper::where('editorial_assigned', $request->user()->id)
            ->where('id', $reviewRound->paper_id)
            ->firstOrFail();

        $completedReviews = $reviewRound->reviews->where('status', 'completed')->count();
        if ($completedReviews < 1) {
            return response()->json([
                'message' => 'At least 1 review must be completed before making a decision.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $editorialDecision = EditorialDecision::create([
                'review_round_id' => $roundId,
                'decision_by' => $request->user()->id,
                'decision' => $request->decision,
                'comments' => $request->comments,
                'made_at' => now(),
            ]);

            $newStatus = match ($request->decision) {
                'accept' => 'accepted',
                'minor_revision', 'major_revision' => 'revision_required',
                'reject' => 'rejected',
            };

            $paper->update([
                'status' => $newStatus,
                'decision_date' => now(),
                'publication_date' => $request->decision === 'accept' ? now() : null,
            ]);

            $reviewRound->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $decisionMessages = [
                'accept' => "Congratulations! Your paper '{$paper->title}' has been accepted.",
                'minor_revision' => "Your paper '{$paper->title}' requires minor revisions.",
                'major_revision' => "Your paper '{$paper->title}' requires major revisions.",
                'reject' => "Your paper '{$paper->title}' has been rejected.",
            ];

            Notification::create([
                'user_id' => $paper->submitted_by,
                'type' => 'editorial_decision',
                'title' => 'Editorial Decision: ' . ucfirst(str_replace('_', ' ', $request->decision)),
                'message' => $decisionMessages[$request->decision] . "\n\nComments: " . $request->comments,
                'data' => json_encode([
                    'paper_id' => $paper->id,
                    'decision' => $request->decision,
                    'round' => $reviewRound->round_number,
                ]),
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'paper_id' => $paper->id,
                'action' => 'decision_made',
                'description' => "Decision: {$request->decision} on Round {$reviewRound->round_number}",
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Decision submitted successfully',
                'decision' => $editorialDecision->load('decidedBy'),
                'paper' => $paper->fresh()->load(['reviewRounds.reviews', 'reviewRounds.editorialDecision']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error making decision',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all reviews for a round
     */
    public function getReviews(Request $request, $roundId)
    {
        $reviews = Review::where('review_round_id', $roundId)
            ->with(['reviewer', 'files'])
            ->get();

        return response()->json([
            'message' => 'Reviews retrieved',
            'reviews' => $reviews,
        ]);
    }

    /**
     * Desk reject a paper
     */
//    public function deskReject(Request $request, $paperId)
//    {
//        $request->validate([
//            'reason' => 'required|string',
//            'comments' => 'required|string|min:20',
//        ]);
//
//        $paper = Paper::where('id', $paperId)
//            ->where('editorial_assigned', $request->user()->id)
//            ->where('status', 'submitted')
//            ->whereDoesntHave('reviewRounds')
//            ->first();
//
//        if (!$paper) {
//            return response()->json(['message' => 'Paper not found or cannot be desk rejected'], 404);
//        }
//
//        $paper->update([
//            'status' => 'rejected',
//            'decision_date' => now(),
//        ]);
//
//        Notification::create([
//            'user_id' => $paper->submitted_by,
//            'type' => 'desk_reject',
//            'title' => 'Paper Desk Rejected',
//            'message' => "Your paper '{$paper->title}' has been rejected.\nReason: {$request->reason}\nComments: {$request->comments}",
//            'data' => json_encode(['paper_id' => $paper->id]),
//        ]);
//
//        return response()->json([
//            'message' => 'Paper desk rejected',
//            'paper' => $paper->fresh(),
//        ]);
//    }

    public function deskReject(Request $request, $paperId)
    {
        $request->validate([
            'reason' => 'required|string',
            'comments' => 'required|string|min:20',
        ]);

        $paper = Paper::where('id', $paperId)
            ->where('editorial_assigned', $request->user()->id)
            ->where('status', 'submitted')
            ->whereDoesntHave('reviewRounds')
            ->first();

        if (!$paper) {
            return response()->json(['message' => 'Paper not found or cannot be desk rejected'], 404);
        }

        $paper->update([
            'status' => 'rejected',
            'decision_date' => now(),
            'desk_reject_reason' => $request->reason,
            'desk_reject_comments' => $request->comments,
        ]);

        // Notification with details
        $reasonLabels = [
            'out_of_scope' => 'Out of Journal Scope',
            'poor_quality' => 'Poor Quality/Incomplete',
            'plagiarism' => 'Plagiarism Concerns',
            'formatting' => 'Does Not Meet Formatting Requirements',
            'not_original' => 'Lack of Originality',
            'other' => 'Other',
        ];

        $reasonText = $reasonLabels[$request->reason] ?? $request->reason;

        Notification::create([
            'user_id' => $paper->submitted_by,
            'type' => 'desk_reject',
            'title' => 'Paper Desk Rejected',
            'message' => "Your paper '{$paper->title}' has been rejected.\n\nReason: {$reasonText}\n\nComments: {$request->comments}",
            'data' => json_encode([
                'paper_id' => $paper->id,
                'reason' => $request->reason,
            ]),
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'desk_reject',
            'description' => "Paper desk rejected. Reason: {$request->reason}",
        ]);

        return response()->json([
            'message' => 'Paper desk rejected',
            'paper' => $paper->fresh(),
        ]);
    }

    /**
     * Publish accepted paper
     */
    public function publish(Request $request, $paperId)
    {
        $paper = Paper::where('id', $paperId)
            ->where('editorial_assigned', $request->user()->id)
            ->where('status', 'accepted')
            ->first();

        if (!$paper) {
            return response()->json(['message' => 'Paper not found or not authorized'], 404);
        }

        $paper->forceFill([
            'is_published' => true,
            'publication_date' => now(),
        ])->save();

        Notification::create([
            'user_id' => $paper->submitted_by,
            'type' => 'paper_published',
            'title' => 'Paper Published',
            'message' => "Your paper '{$paper->title}' has been published!",
            'data' => json_encode(['paper_id' => $paper->id]),
        ]);

        $paper = Paper::with(['submitter', 'category', 'files', 'reviewRounds.reviews.reviewer', 'reviewRounds.editorialDecision.decidedBy'])
            ->find($paper->id);

        return response()->json([
            'message' => 'Paper published successfully',
            'paper' => $paper,
        ]);
    }

    /**
     * Unpublish paper
     */
    public function unpublish(Request $request, $paperId)
    {
        $paper = Paper::where('id', $paperId)
            ->where('editorial_assigned', $request->user()->id)
            ->where('is_published', true)
            ->first();

        if (!$paper) {
            return response()->json(['message' => 'Paper not found or not authorized'], 404);
        }

        $paper->forceFill([
            'is_published' => false,
            'publication_date' => null,
        ])->save();

        $paper = Paper::with(['submitter', 'category', 'files', 'reviewRounds.reviews.reviewer', 'reviewRounds.editorialDecision.decidedBy'])
            ->find($paper->id);

        return response()->json([
            'message' => 'Paper unpublished successfully',
            'paper' => $paper,
        ]);
    }
}
