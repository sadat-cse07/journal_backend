<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaperFile;
use Illuminate\Support\Facades\Storage;

class FileDownloadController extends Controller
{
    public function download($fileId)
    {
        $file = PaperFile::findOrFail($fileId);
        $user = request()->user();
        $paper = $file->paper;

        $hasAccess = false;

        // PUBLIC access for published papers - NO AUTH REQUIRED
        if ($paper->is_published && $paper->status === 'accepted') {
            $hasAccess = true;
        }

        // Authenticated users
        if ($user) {
            if ($paper->submitted_by === $user->id) $hasAccess = true;
            if ($paper->editorial_assigned === $user->id) $hasAccess = true;
            if ($user->hasRole('admin')) $hasAccess = true;
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($file->file_path, $file->file_name);
    }
}
