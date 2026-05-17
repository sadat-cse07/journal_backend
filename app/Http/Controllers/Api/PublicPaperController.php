<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Paper;
use Illuminate\Http\Request;

class PublicPaperController extends Controller
{
    public function index(Request $request)
    {
        // Debug log
        \Log::info('Public papers requested', [
            'search' => $request->search,
            'category' => $request->category,
        ]);

        $papers = Paper::where('is_published', true)
            ->where('status', 'accepted')
            ->with(['submitter:id,first_name,last_name,affiliation', 'category:id,name,slug'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('title', 'like', "%{$request->search}%")
                        ->orWhere('abstract', 'like', "%{$request->search}%");
                });
            })
            ->when($request->category, function ($q) use ($request) {
                $q->where('category_id', $request->category);
            })
            ->latest('publication_date')
            ->paginate($request->per_page ?? 12);

        // Add plain text preview
        $papers->getCollection()->transform(function ($paper) {
            $paper->abstract_preview = strip_tags(substr($paper->abstract, 0, 300));
            return $paper;
        });

        \Log::info('Public papers found: ' . $papers->total());

        return response()->json([
            'papers' => $papers,
        ]);
    }

    public function show($id)
    {
        $paper = Paper::where('is_published', true)
            ->where('status', 'accepted')
            ->with([
                'submitter:id,first_name,last_name,affiliation,email',
                'category:id,name,slug',
                'files',
            ])
            ->findOrFail($id);

        return response()->json([
            'paper' => $paper,
        ]);
    }

    public function count()
    {
        $count = Paper::where('is_published', true)
            ->where('status', 'accepted')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function categories()
    {
        $categories = Category::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['categories' => $categories]);
    }
}
