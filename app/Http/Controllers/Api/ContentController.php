<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Models\Salon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    /**
     * The authenticated salon's own posts, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $salon = $this->salonFor($request);

        return response()->json(
            $salon->contentPosts()
                ->withCount('likes')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (ContentPost $post) => $this->present($post))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $salon = $this->salonFor($request);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $data['image'];
        $path = $file->store("content/{$salon->id}", 'local');

        $post = $salon->contentPosts()->create([
            'image_path' => $path,
            'image_mime' => $file->getMimeType(),
            'caption' => $data['caption'] ?? null,
        ]);

        return response()->json($this->present($post->loadCount('likes')), 201);
    }

    public function destroy(Request $request, ContentPost $post): JsonResponse
    {
        $salon = $this->salonFor($request);

        if ($post->salon_id !== $salon->id) {
            abort(403, 'This post does not belong to your business.');
        }

        Storage::disk('local')->delete($post->image_path);
        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    /**
     * Streams the actual image bytes. Kept behind auth:sanctum like every
     * other endpoint rather than relying on a public storage symlink being
     * set up at deploy time — the client attaches its bearer token as a
     * request header when loading the image.
     */
    public function showImage(Request $request, ContentPost $post): Response
    {
        if (! Storage::disk('local')->exists($post->image_path)) {
            abort(404);
        }

        return response(Storage::disk('local')->get($post->image_path))
            ->header('Content-Type', $post->image_mime)
            ->header('Cache-Control', 'private, max-age=86400');
    }

    /**
     * A specific salon's posts, for clients browsing in Discover — includes
     * whether the current client has already liked each one.
     */
    public function forSalon(Request $request, Salon $salon): JsonResponse
    {
        $clientId = $request->user()->client?->id;

        return response()->json(
            $salon->contentPosts()
                ->withCount('likes')
                ->with(['likes' => fn ($q) => $clientId ? $q->where('client_id', $clientId) : $q->whereRaw('1 = 0')])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (ContentPost $post) => [
                    ...$this->present($post),
                    'liked_by_me' => $post->likes->isNotEmpty(),
                ])
        );
    }

    public function toggleLike(Request $request, ContentPost $post): JsonResponse
    {
        $client = $request->user()->client;

        if (! $client) {
            throw ValidationException::withMessages([
                'client' => 'Only client accounts can like content.',
            ]);
        }

        $existing = $post->likes()->where('client_id', $client->id)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $post->likes()->create(['client_id' => $client->id]);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    private function salonFor(Request $request): Salon
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile before managing content.',
            ]);
        }

        return $salon;
    }

    private function present(ContentPost $post): array
    {
        return [
            'id' => $post->id,
            'image_url' => route('content.image', $post),
            'caption' => $post->caption,
            'likes_count' => $post->likes_count ?? 0,
            'created_at' => $post->created_at,
        ];
    }
}
