<?php

namespace App\Http\Controllers\Api\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Notification;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * Get reviewer dashboard
     */
    public function dashboard(Request $request)
    {
        $reviewerId = $request->user()->id;
        
        $stats = [
            'pending_reviews' => Review::where('reviewer_id', $reviewerId)
                ->where('status', 'pending')->count(),
            'active_reviews' => Review::where('reviewer_id', $reviewerId)
                ->where('status', 'in_progress')->count(),
            'completed_reviews' => Review::where('reviewer_id', $reviewerId)
                ->where('status', 'completed')->count(),
            'overdue_reviews' => Review::where('reviewer_id', $reviewerId)
                ->where('status', 'in_progress')
                ->where('due_date', '<', now())
                ->count(),
        ];
        
        return response()->json([
            'message' => 'Dashboard retrieved',
            'stats' => $stats,
        ]);
    }

    /**
     * List assigned reviews
     */
    public function index(Request $request)
    {
        $reviews = Review::where('reviewer_id', $request->user()->id)
            ->with([
                'reviewRound.paper' => function($q) {
                    $q->select('id', 'title', 'abstract', 'status');
                }
            ])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest('assigned_at')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'message' => 'Reviews retrieved',
            'reviews' => $reviews,
        ]);
    }

    /**
     * Show review details
     */
/**
 * Show review details
 */
public function show(Request $request, $id)
{
    $review = Review::where('reviewer_id', $request->user()->id)
        ->with([
            'reviewRound.paper' => function($q) {
                $q->with(['files', 'category', 'submitter']);
            },
            'files'
        ])
        ->findOrFail($id);

    return response()->json([
        'message' => 'Review details retrieved',
        'review' => $review,
    ]);
}

    /**
     * Accept review invitation
     */
    public function accept(Request $request, $id)
    {
        $review = Review::where('reviewer_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $review->update(['status' => 'in_progress']);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $review->reviewRound->paper_id,
            'action' => 'review_accepted',
            'description' => 'Review invitation accepted',
        ]);

        return response()->json([
            'message' => 'Review accepted',
            'review' => $review->fresh(),
        ]);
    }

    /**
     * Decline review invitation
     */
    public function decline(Request $request, $id)
    {
        $review = Review::where('reviewer_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $review->update(['status' => 'declined']);

        // Update reviewer profile
        $profile = $request->user()->reviewerProfile;
        if ($profile) {
            $profile->decrementReviews();
        }

        // Notify editorial
        $paper = $review->reviewRound->paper;
        if ($paper && $paper->editorial_assigned) {
            Notification::create([
                'user_id' => $paper->editorial_assigned,
                'type' => 'review_declined',
                'title' => 'Review Declined',
                'message' => $request->user()->full_name . ' declined to review paper.',
                'data' => ['paper_id' => $paper->id],
            ]);
        }

        return response()->json([
            'message' => 'Review declined',
        ]);
    }

    /**
     * Submit review
     */
    public function submit(Request $request, $id)
    {
        $request->validate([
            'decision' => ['required', Rule::in(['accept', 'minor_revision', 'major_revision', 'reject'])],
            'confidential_comments' => 'nullable|string',
            'comments_for_author' => 'required|string',
            'comments_for_editor' => 'nullable|string',
            'rating_originality' => 'required|integer|between:1,5',
            'rating_methodology' => 'required|integer|between:1,5',
            'rating_clarity' => 'required|integer|between:1,5',
            'rating_significance' => 'required|integer|between:1,5',
            'overall_recommendation' => 'required|integer|between:1,5',
        ]);

        $review = Review::where('reviewer_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            $review->update([
                'status' => 'completed',
                'decision' => $request->decision,
                'confidential_comments' => $request->confidential_comments,
                'comments_for_author' => $request->comments_for_author,
                'comments_for_editor' => $request->comments_for_editor,
                'rating_originality' => $request->rating_originality,
                'rating_methodology' => $request->rating_methodology,
                'rating_clarity' => $request->rating_clarity,
                'rating_significance' => $request->rating_significance,
                'overall_recommendation' => $request->overall_recommendation,
                'submitted_at' => now(),
            ]);

            // Update reviewer profile
            $profile = $request->user()->reviewerProfile;
            if ($profile) {
                $profile->decrementReviews();
                $profile->increment('total_reviews_completed');
                // Recalculate average rating
                $profile->update([
                    'average_rating' => Review::where('reviewer_id', $request->user()->id)
                        ->where('status', 'completed')
                        ->avg('overall_recommendation') ?? 0
                ]);
            }

            // Notify editorial
            $paper = $review->reviewRound->paper;
            if ($paper && $paper->editorial_assigned) {
                Notification::create([
                    'user_id' => $paper->editorial_assigned,
                    'type' => 'review_completed',
                    'title' => 'Review Submitted',
                    'message' => 'A review has been submitted for paper.',
                    'data' => ['paper_id' => $paper->id],
                ]);
            }

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'paper_id' => $paper->id,
                'action' => 'review_submitted',
                'description' => 'Review submitted',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Review submitted successfully',
                'review' => $review->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error submitting review',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}