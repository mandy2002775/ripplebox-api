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
     * Create the business profile for the authenticated salon owner.
     * One salon per user — this is a one-time setup step (screen 3),
     * not a general-purpose update endpoint.
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
}
