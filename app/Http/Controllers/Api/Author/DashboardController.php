<?php

namespace App\Http\Controllers\Api\Author;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get author dashboard stats
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_papers' => Paper::where('submitted_by', $user->id)->count(),
            'draft' => Paper::where('submitted_by', $user->id)->where('status', 'draft')->count(),
            'submitted' => Paper::where('submitted_by', $user->id)->where('status', 'submitted')->count(),
            'under_review' => Paper::where('submitted_by', $user->id)
                ->whereIn('status', ['submitted', 'under_review'])->count(),
            'revision_required' => Paper::where('submitted_by', $user->id)
                ->where('status', 'revision_required')->count(),
            'accepted' => Paper::where('submitted_by', $user->id)
                ->where('status', 'accepted')->count(),
            'published' => Paper::where('submitted_by', $user->id)
                ->where('is_published', true)->count(),
            'unpublished' => Paper::where('submitted_by', $user->id)
                ->where('status', 'accepted')
                ->where('is_published', false)->count(),
            'rejected' => Paper::where('submitted_by', $user->id)
                ->where('status', 'rejected')->count(),
            'withdrawn' => Paper::where('submitted_by', $user->id)
                ->where('status', 'withdrawn')->count(),
        ];

        // Recent papers (last 5)
        $recentPapers = Paper::where('submitted_by', $user->id)
            ->with('category')
            ->latest()
            ->take(5)
            ->get(['id', 'uuid', 'title', 'status', 'is_published', 'submission_date', 'current_round']);

        return response()->json([
            'message' => 'Author Dashboard',
            'stats' => $stats,
            'recent_papers' => $recentPapers,
        ]);
    }
}
