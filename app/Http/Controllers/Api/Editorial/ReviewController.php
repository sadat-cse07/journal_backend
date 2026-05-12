<?php

namespace App\Http\Controllers\Api\Editorial;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Models\ReviewRound;
use App\Models\Review;
use App\Models\EditorialDecision;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\ReviewerProfile;
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
            'completed_today' => Paper::where('editorial_assigned', $editorialId)
                ->whereIn('status', ['accepted', 'rejected'])
                ->whereDate('decision_date', today())->count(),
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
            ->when($request->search, function($q) use ($request) {
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
            'reviewRounds' => function($q) {
                $q->with([
                    'reviews' => function($q) {
                        $q->with('reviewer');
                    },
                    'editorialDecision.decidedBy'
                ]);
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

    // Increment round number
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
/**
 * Get available reviewers
 */
public function availableReviewers(Request $request)
{
    $reviewers = User::role('reviewer')
        ->where('status', 'active')
        ->whereHas('reviewerProfile', function($q) {
            $q->where('availability_status', 'available')
              ->whereRaw('current_reviews < max_reviews');
        })
        ->with('reviewerProfile')
        ->get()
        ->map(function($reviewer) {
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
    
    // Verify the round belongs to this editor's paper
    $paper = Paper::where('editorial_assigned', $request->user()->id)
        ->where('id', $reviewRound->paper_id)
        ->first();
        
    if (!$paper) {
        return response()->json([
            'message' => 'You are not authorized to manage this paper',
        ], 403);
    }

    $assignedReviewers = [];
    $dueDate = $request->due_date ?? now()->addDays(21);

    foreach ($request->reviewer_ids as $reviewerId) {
        // Check if already assigned to this round
        $exists = Review::where('review_round_id', $roundId)
            ->where('reviewer_id', $reviewerId)
            ->exists();

        if ($exists) {
            continue;
        }

        // Check if reviewer is paper author
        $isAuthor = $paper->authors()->where('user_id', $reviewerId)->exists();
        if ($isAuthor) {
            continue;
        }

        // Create the review assignment
        $review = Review::create([
            'review_round_id' => $roundId,
            'reviewer_id' => $reviewerId,
            'status' => 'pending',
            'assigned_at' => now(),
            'due_date' => $dueDate,
        ]);

        // Update reviewer profile counter
        $reviewerProfile = \App\Models\ReviewerProfile::where('user_id', $reviewerId)->first();
        if ($reviewerProfile) {
            $reviewerProfile->increment('current_reviews');
            // Check if they're at max capacity
            if ($reviewerProfile->fresh()->current_reviews >= $reviewerProfile->max_reviews) {
                $reviewerProfile->update(['availability_status' => 'busy']);
            }
        }

        // Send notification
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
        'description' => count($assignedReviewers) . ' reviewer(s) assigned to round ' . $reviewRound->round_number,
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
            'comments' => 'required|string',
            'revision_deadline' => 'nullable|date|after:today|required_if:decision,minor_revision,major_revision',
        ]);

        $reviewRound = ReviewRound::with('reviews')->findOrFail($roundId);
        
        $paper = Paper::where('editorial_assigned', $request->user()->id)
            ->where('id', $reviewRound->paper_id)
            ->firstOrFail();

        // Check if minimum reviews are complete
        $completedReviews = $reviewRound->reviews->where('status', 'completed')->count();
        if ($completedReviews < 2) {
            return response()->json([
                'message' => 'At least 2 reviews must be completed before making a decision',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create decision
            $decision = EditorialDecision::create([
                'review_round_id' => $roundId,
                'decision_by' => $request->user()->id,
                'decision' => $request->decision,
                'comments' => $request->comments,
                'made_at' => now(),
            ]);

            // Update paper status
            $newStatus = match($request->decision) {
                'accept' => 'accepted',
                'minor_revision', 'major_revision' => 'revision_required',
                'reject' => 'rejected',
            };

            $paper->update([
                'status' => $newStatus,
                'decision_date' => now(),
            ]);

            // Mark round as completed
            $reviewRound->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Notify author
            $decisionMessages = [
                'accept' => 'Your paper has been accepted!',
                'minor_revision' => 'Minor revisions requested for your paper.',
                'major_revision' => 'Major revisions requested for your paper.',
                'reject' => 'Your paper has been rejected.',
            ];

            Notification::create([
                'user_id' => $paper->submitted_by,
                'type' => 'editorial_decision',
                'title' => 'Editorial Decision',
                'message' => $decisionMessages[$request->decision],
                'data' => [
                    'paper_id' => $paper->id,
                    'decision' => $request->decision,
                    'deadline' => $request->revision_deadline,
                ],
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'paper_id' => $paper->id,
                'action' => 'decision_made',
                'description' => "Decision: {$request->decision}",
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Decision submitted successfully',
                'decision' => $decision,
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
}