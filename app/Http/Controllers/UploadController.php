<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $path = $request->file('file')->storePublicly($directory, 'public');

        if ($path === false) {
            return response()->json(['message' => 'Unable to store the uploaded file.'], 500);
        }

        return response()->json([
            'url' => '/storage/'.$path,
            'path' => $path,
            'filename' => $request->file('file')->getClientOriginalName(),
            'success' => 'File uploaded successfully.',
        ]);
    }
}
