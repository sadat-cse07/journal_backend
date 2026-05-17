<?php

namespace App\Http\Controllers\Api\Author;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Paper;
use App\Models\PaperAuthor;
use App\Models\PaperFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaperController extends Controller
{
    /**
     * List author's papers
     */

    public function index(Request $request)
    {
        try {
            $papers = Paper::where('submitted_by', $request->user()->id)
                ->when($request->status, function ($q) use ($request) {
                    return $q->where('status', $request->status);
                })
                ->when($request->search, function ($q) use ($request) {
                    return $q->where('title', 'like', "%{$request->search}%");
                })
                ->latest()
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'message' => 'Papers retrieved successfully',
                'papers' => $papers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving papers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show paper details
     */
    public function show($id, Request $request)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->with([
                'authors.user',
                'category',
                'files',
                'reviewRounds.reviews.reviewer',
                'reviewRounds.editorialDecision.decidedBy',
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Paper details retrieved',
            'paper' => $paper,
        ]);
    }

    /**
     * Delete draft paper
     */
    public function destroy(Request $request, $id)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->where('status', 'draft')
            ->findOrFail($id);

        // Delete associated files
        foreach ($paper->files as $file) {
            Storage::delete($file->file_path);
        }

        $paper->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'paper_deleted',
            'description' => 'Draft paper deleted',
        ]);

        return response()->json([
            'message' => 'Paper deleted successfully',
        ]);
    }

    /**
     * Submit paper for review
     */
    public function submit(Request $request, $id)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->where('status', 'draft')
            ->findOrFail($id);

        // Check if paper has at least one file
        if ($paper->files()->count() === 0) {
            return response()->json([
                'message' => 'Please upload at least one manuscript file before submitting',
            ], 422);
        }

        // Auto-assign editorial member (simplified: assign editorial with least papers)
        $editorial = \App\Models\User::whereHas('roles', function ($q) {
            $q->where('name', 'editorial');
        })
            ->withCount('assignedPapers')
            ->orderBy('assigned_papers_count')
            ->first();

        if (!$editorial) {
            return response()->json([
                'message' => 'No editorial members available',
            ], 500);
        }

        $paper->update([
            'status' => 'submitted',
            'submission_date' => now(),
            'editorial_assigned' => $editorial->id,
        ]);

        // Create notification for editorial
        \App\Models\Notification::create([
            'user_id' => $editorial->id,
            'type' => 'new_submission',
            'title' => 'New Paper Submission',
            'message' => "New paper '{$paper->title}' has been submitted and assigned to you.",
            'data' => ['paper_id' => $paper->id],
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'paper_submitted',
            'description' => 'Paper submitted for review',
        ]);

        return response()->json([
            'message' => 'Paper submitted successfully',
            'paper' => $paper->fresh(),
        ]);
    }

    /**
     * Update paper (draft only)
     */
//    public function update(Request $request, $id)
//    {
//        $paper = Paper::where('submitted_by', $request->user()->id)
//            ->where('status', 'draft')
//            ->findOrFail($id);
//
//        $request->validate([
//            'title' => 'sometimes|string|max:500',
//            'abstract' => 'sometimes|string',
//            'keywords' => 'nullable|array',
//            'category_id' => 'nullable|exists:categories,id',
//            'paper_type' => ['sometimes', Rule::in(['research_article', 'review_article', 'case_study', 'technical_note'])],
//        ]);
//
//        $paper->update($request->only([
//            'title', 'abstract', 'keywords', 'category_id', 'paper_type'
//        ]));
//
//        return response()->json([
//            'message' => 'Paper updated successfully',
//            'paper' => $paper->fresh(['authors.user', 'category']),
//        ]);
//    }

    /**
     * Update paper (draft only)
     */
    /**
     * Update paper (draft only)
     */
    public function update(Request $request, $id)
    {
        $paper = Paper::find($id);

        if (!$paper) {
            return response()->json(['message' => 'Paper not found'], 404);
        }

        if ($paper->submitted_by != $request->user()->id) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        if ($paper->status !== 'draft' && $paper->status !== 'revision_required') {
            return response()->json(['message' => 'Only drafts can be edited. Current status: ' . $paper->status], 422);
        }

        // Simple direct update
        $paper->title = $request->title ?? $paper->title;
        $paper->abstract = $request->abstract ?? $paper->abstract;
        $paper->keywords = $request->keywords ?? $paper->keywords;
        $paper->category_id = $request->category_id ?? $paper->category_id;
        $paper->paper_type = $request->paper_type ?? $paper->paper_type;
        $paper->save();

        return response()->json([
            'message' => 'Paper updated',
            'paper' => $paper->fresh(),
        ]);
    }

    /**
     * Withdraw paper
     */
    public function withdraw(Request $request, $id)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->whereIn('status', ['submitted', 'under_review', 'revision_required'])
            ->findOrFail($id);

        $paper->update(['status' => 'withdrawn']);

        // Notify editorial
        if ($paper->editorial_assigned) {
            \App\Models\Notification::create([
                'user_id' => $paper->editorial_assigned,
                'type' => 'paper_withdrawn',
                'title' => 'Paper Withdrawn',
                'message' => "Paper '{$paper->title}' has been withdrawn by the author.",
                'data' => ['paper_id' => $paper->id],
            ]);
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'paper_withdrawn',
            'description' => 'Paper withdrawn by author',
        ]);

        return response()->json([
            'message' => 'Paper withdrawn successfully',
        ]);
    }

    /**
     * Upload file to paper
     */
    public function uploadFile(Request $request, $id)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->whereIn('status', ['draft', 'revision_required'])
            ->findOrFail($id);

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,txt', // 10MB max
            'file_type' => ['required', Rule::in(['manuscript', 'cover_letter', 'supplementary', 'revision', 'figure', 'table'])],
        ]);

        try {
            $file = $request->file('file');

            // Make sure storage directory exists
            $directory = 'papers/' . $paper->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store the file
            $path = $file->store($directory, 'public');

            // Get current version for this file type
            $currentVersion = $paper->files()
                ->where('file_type', $request->file_type)
                ->max('version') ?? 0;

            $paperFile = PaperFile::create([
                'paper_id' => $paper->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $request->file_type,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'version' => $currentVersion + 1,
                'uploaded_by' => $request->user()->id,
                'uploaded_at' => now(),
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'paper_id' => $paper->id,
                'action' => 'file_uploaded',
                'description' => "File uploaded: {$file->getClientOriginalName()}",
            ]);

            return response()->json([
                'message' => 'File uploaded successfully',
                'file' => $paperFile->load('uploader'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error uploading file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload file to paper
     */

    /**
     * Create new paper (draft)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:500',
            'abstract' => 'required|string',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string',
            'category_id' => 'nullable|exists:categories,id',
            'paper_type' => ['required', Rule::in(['research_article', 'review_article', 'case_study', 'technical_note'])],
            'co_authors' => 'nullable|array',
            'co_authors.*.email' => 'required|email|exists:users,email',
            'co_authors.*.author_order' => 'required|integer',
            'co_authors.*.is_corresponding' => 'boolean',
        ]);

        try {
            // Create paper
            $paper = Paper::create([
                'title' => $request->title,
                'abstract' => $request->abstract,
                'keywords' => $request->keywords,
                'category_id' => $request->category_id,
                'paper_type' => $request->paper_type,
                'status' => 'draft',
                'submitted_by' => $request->user()->id,
            ]);

            // Add submitting author as first author
            PaperAuthor::create([
                'paper_id' => $paper->id,
                'user_id' => $request->user()->id,
                'author_order' => 1,
                'is_corresponding' => true,
            ]);

            // Add co-authors if any
            if ($request->has('co_authors')) {
                foreach ($request->co_authors as $coAuthor) {
                    $coAuthorUser = \App\Models\User::where('email', $coAuthor['email'])->first();

                    if ($coAuthorUser) {
                        $exists = PaperAuthor::where('paper_id', $paper->id)
                            ->where('user_id', $coAuthorUser->id)
                            ->exists();

                        if (!$exists) {
                            PaperAuthor::create([
                                'paper_id' => $paper->id,
                                'user_id' => $coAuthorUser->id,
                                'author_order' => $coAuthor['author_order'],
                                'is_corresponding' => $coAuthor['is_corresponding'] ?? false,
                            ]);
                        }
                    }
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'paper_id' => $paper->id,
                'action' => 'paper_created',
                'description' => 'Paper created as draft',
            ]);

            return response()->json([
                'message' => 'Paper created successfully',
                'paper' => $paper->load(['authors.user', 'category']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating paper',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit revision
     */
    public function submitRevision(Request $request, $id)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->where('status', 'revision_required')
            ->findOrFail($id);

        $request->validate([
            'response_to_reviewers' => 'required|string',
        ]);

        $paper->update([
            'status' => 'under_review',
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'paper_id' => $paper->id,
            'action' => 'revision_submitted',
            'description' => 'Revision submitted',
            'metadata' => ['response' => $request->response_to_reviewers],
        ]);

        return response()->json([
            'message' => 'Revision submitted successfully',
            'paper' => $paper->fresh(),
        ]);
    }

    /**
     * Get categories list
     */
    public function categories()
    {
        $categories = Category::active()->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Delete a file from paper
     */
    /**
     * Delete a file from paper
     */
    public function deleteFile(Request $request, $paperId, $fileId)
    {
        $paper = Paper::where('submitted_by', $request->user()->id)
            ->whereIn('status', ['draft', 'revision_required'])
            ->find($paperId);

        if (!$paper) {
            return response()->json(['message' => 'Paper not found'], 404);
        }

        $file = \App\Models\PaperFile::where('paper_id', $paperId)
            ->where('id', $fileId)
            ->first();

        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Delete from storage
        if (\Storage::disk('public')->exists($file->file_path)) {
            \Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();

        return response()->json(['message' => 'File deleted']);
    }
}
