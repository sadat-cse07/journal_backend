<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        try {
            $file = $request->file('image');

            // Create directory if not exists
            if (!Storage::disk('public')->exists('abstracts/images')) {
                Storage::disk('public')->makeDirectory('abstracts/images', 0755, true);
            }

            $filename = Str::uuid() . '.jpg';
            $path = 'abstracts/images/' . $filename;

            // Resize and compress image
            $img = imagecreatefromstring(file_get_contents($file->getRealPath()));

            if ($img) {
                // Get original dimensions
                $width = imagesx($img);
                $height = imagesy($img);

                // Max dimensions
                $maxWidth = 1200;
                $maxHeight = 1200;

                // Calculate new dimensions
                if ($width > $maxWidth || $height > $maxHeight) {
                    $ratio = min($maxWidth / $width, $maxHeight / $height);
                    $newWidth = round($width * $ratio);
                    $newHeight = round($height * $ratio);

                    $newImg = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($img);
                    $img = $newImg;
                }

                // Save as JPEG with compression
                $fullPath = Storage::disk('public')->path($path);
                imagejpeg($img, $fullPath, 80); // 80% quality
                imagedestroy($img);
            } else {
                // Fallback: just store the file
                $path = $file->storeAs('abstracts/images', $filename, 'public');
            }

            $url = asset('storage/' . $path);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url' => $url,
                'path' => $path,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error uploading image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
