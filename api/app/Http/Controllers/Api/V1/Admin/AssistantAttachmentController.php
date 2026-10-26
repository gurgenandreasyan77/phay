<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Assistant\Attachments;
use App\Assistant\BusinessAssistant;
use App\Http\Controllers\Controller;
use App\Models\AssistantAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files for the AI chat. Uploaded before the question is sent (so the
 * person sees them attached), sent with it by id (AssistantController),
 * read back only by the person who attached them, through the API: they
 * live on the private disk and never get a public URL.
 */
class AssistantAttachmentController extends Controller
{
    public function __construct(private readonly Attachments $attachments) {}

    public function store(Request $request, BusinessAssistant $assistant): JsonResponse
    {
        // No point keeping a file for a question that can't be asked.
        if (! $assistant->isConfigured()) {
            return response()->json(['message' => 'The AI assistant is not connected yet.', 'code' => 'not_configured'], 503);
        }
        if ($assistant->limitReached()) {
            return response()->json([
                'message' => 'This subscription month\'s AI assistant requests are used up.',
                'code' => 'limit_reached',
                'usage' => $assistant->usage(),
                'offer' => $assistant->offer(),
            ], 429);
        }

        $request->validate([
            // The type is checked by content in Attachments::inspect; this is the upper bound.
            'file' => ['required', 'file', 'max:'.intdiv(Attachments::MAX_PDF_BYTES, 1024)],
        ], ['file.max' => 'Ֆայլը շատ մեծ է (առավելագույնը 10 ՄԲ)։', 'file.required' => 'Ընտրեք ֆայլ։']);

        $attachment = $this->attachments->upload($request->file('file'), $request->user());

        return response()->json(['data' => $attachment->present()], 201);
    }

    /** The file itself, shown in the chat (inline, never run as a page); ?download=1 saves it. */
    public function file(Request $request, AssistantAttachment $assistantAttachment): StreamedResponse
    {
        $file = $this->own($request, $assistantAttachment);

        return self::serve($file->path, $file->name, $file->mime === 'text/plain' ? 'text/plain; charset=utf-8' : $file->mime, $request->boolean('download'));
    }

    /** Takes back a file not sent yet; a sent one goes with its conversation. */
    public function destroy(Request $request, AssistantAttachment $assistantAttachment): Response
    {
        $file = $this->own($request, $assistantAttachment);
        abort_if($file->assistant_message_id !== null, 409, 'This file was already sent.');

        $file->delete();
        $this->attachments->delete([$file->path]);

        return response()->noContent();
    }

    /**
     * A private file as the browser should get it: its own type, shown
     * inline, never sniffed into something else nor run as a page.
     */
    public static function serve(string $path, string $name, string $type, bool $download = false): StreamedResponse
    {
        $disk = Storage::disk(Attachments::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $name, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], $download ? 'attachment' : 'inline');
    }

    /** Another person's file behaves as if it doesn't exist. */
    private function own(Request $request, AssistantAttachment $file): AssistantAttachment
    {
        abort_unless((int) $file->user_id === (int) $request->user()->id, 404);

        return $file;
    }
}
