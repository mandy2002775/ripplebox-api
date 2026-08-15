<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalonController extends Controller
{
    /**
     * List salons for client-side discovery (screen 7's "Nearby salons").
     * No geo-distance sort yet — that needs Google Places, which isn't
     * wired up — so this is ordered by name for now.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Salon::orderBy('business_name')->get(['id', 'business_name', 'location', 'logo_url'])
        );
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
            'location' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $salon = Salon::create([
            ...$data,
            'user_id' => $user->id,
        ]);

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
