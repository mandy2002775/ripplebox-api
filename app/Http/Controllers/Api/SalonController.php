<?php

namespace App\Http\Controllers\Api;

use App\Enums\SalonCategory;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalonController extends Controller
{
    /**
     * List salons for client-side discovery (screen 7's "Nearby salons",
     * which pairs each salon with a distance and a headline reward — no
     * geo-distance sort yet, that needs Google Places, which isn't wired
     * up, but the reward preview is real: each salon's highest-value
     * active reward, if it has one). Optionally scoped to one category.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['sometimes', Rule::enum(SalonCategory::class)],
        ]);

        $salons = Salon::query()
            ->when($data['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderBy('business_name')
            ->with(['rewards' => fn ($q) => $q->where('is_active', true)->orderByDesc('reward_value')->limit(1)])
            ->get(['id', 'business_name', 'category', 'location', 'logo_url']);

        $favoritedIds = $request->user()->client
            ?->favoriteSalons()->pluck('salon_id')->all() ?? [];

        return response()->json($salons->map(fn (Salon $salon) => [
            'id' => $salon->id,
            'business_name' => $salon->business_name,
            'category' => $salon->category,
            'location' => $salon->location,
            'logo_url' => $salon->logo_url,
            'top_reward' => $salon->rewards->first()?->description,
            'is_favorited' => in_array($salon->id, $favoritedIds, true),
        ]));
    }

    /**
     * Create the business profile for the authenticated salon owner.
     * One salon per user — this is a one-time setup step (screen 3).
     * Editing an existing profile is `update()` below, not this.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type !== UserType::Salon) {
            throw ValidationException::withMessages([
                'user_type' => 'Only salon accounts can create a business profile.',
            ]);
        }

        if ($user->salon) {
            return response()->json([
                'message' => 'A business profile already exists for this account.',
            ], 409);
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(SalonCategory::class)],
            'location' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
        ]);

        // The $user->salon check above isn't atomic with this create() — two
        // concurrent submits could both pass it. The DB-level unique
        // constraint on salons.user_id is the real guarantee; this turns the
        // race loser's failure into the same clean 409 instead of a raw 500.
        try {
            $salon = Salon::create([
                ...$data,
                'user_id' => $user->id,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'A business profile already exists for this account.',
                ], 409);
            }

            throw $e;
        }

        return response()->json($salon, 201);
    }

    /**
     * Edit an existing business profile — the settings-gear icon on the
     * salon dashboard routes back to the same profile-setup form, but for
     * editing rather than one-time creation.
     */
    public function update(Request $request): JsonResponse
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile first.',
            ]);
        }

        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(SalonCategory::class)],
            'location' => ['sometimes', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $salon->update($data);

        return response()->json($salon);
    }
}
