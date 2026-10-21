<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class UploadController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['image', 'file'])],
        ]);

        $request->validate([
            'file' => [
                'required',
                'file',
                $validated['type'] === 'image'
                    ? 'mimes:jpg,jpeg,png,gif,webp'
                    : 'mimes:pdf,doc,docx,txt,zip',
                'max:10240',
            ],
        ]);

        $directory = "users/{$portfolio->user_id}/templates/{$portfolio->template_id}/portfolios/{$portfolio->id}";
        if (! is_string(config('filesystems.disks.r2.url')) || trim(config('filesystems.disks.r2.url')) === '') {
            throw new RuntimeException('R2_URL must be configured before uploading files.');
        }

        $disk = 'r2';
        $path = $request->file('file')->store($directory, $disk);

        if ($path === false) {
            return response()->json(['message' => 'Unable to store the uploaded file.'], 500);
        }

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
            'filename' => $request->file('file')->getClientOriginalName(),
            'success' => 'File uploaded successfully.',
        ]);
    }
}
