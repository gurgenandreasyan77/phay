<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    /**
     * Generic authenticated upload used by every admin media field that
     * today expects a hand-typed path string (service images, gallery
     * before/after pairs, process videos). Returns a path the admin form
     * stores in the same string field a manually-placed file path would
     * have gone in — no new "media" concept, just a real endpoint behind
     * the field that used to require the file to already exist under
     * `public/`. Video and image folders get different mime/size rules,
     * since a short process clip is legitimately much larger than a photo.
     */
    private const IMAGE_FOLDERS = ['services', 'service-packages', 'service-gallery', 'additional-services'];

    private const VIDEO_FOLDERS = ['service-videos'];

    public function store(Request $request): JsonResponse
    {
        $folder = $request->string('folder')->toString();

        $request->validate([
            'folder' => ['required', Rule::in([...self::IMAGE_FOLDERS, ...self::VIDEO_FOLDERS])],
            'file' => in_array($folder, self::VIDEO_FOLDERS, true)
                ? ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:81920']
                : ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $filename = Str::uuid()->toString().'.'.$request->file('file')->extension();
        // One folder per business, so files can be listed, moved or deleted
        // per business later (files uploaded before multi-tenancy stay where
        // they are; their stored URLs don't change).
        $directory = 'uploads/'.app(TenantContext::class)->id().'/'.$folder;
        $path = $request->file('file')->storeAs($directory, $filename, 'public');

        return response()->json([
            'path' => Storage::disk('public')->url($path),
        ], 201);
    }
}
