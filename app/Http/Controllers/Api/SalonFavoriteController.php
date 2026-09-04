<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\SalonFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalonFavoriteController extends Controller
{
    /**
     * The authenticated client's favorited salons — same shape as
     * SalonController::index so the frontend can reuse its salon-row UI.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->clientFor($request);

        $salons = $client->favoriteSalons()
            ->with(['salon' => fn ($q) => $q->with([
                'rewards' => fn ($r) => $r->where('is_active', true)->orderByDesc('reward_value')->limit(1),
            ])])
            ->latest('created_at')
            ->get()
            ->pluck('salon')
            ->filter();

        return response()->json($salons->map(fn (Salon $salon) => [
            'id' => $salon->id,
            'business_name' => $salon->business_name,
            'category' => $salon->category,
            'location' => $salon->location,
            'logo_url' => $salon->logo_url,
            'top_reward' => $salon->rewards->first()?->description,
            'is_favorited' => true,
        ])->values());
    }

    public function toggle(Request $request, Salon $salon): JsonResponse
    {
        $client = $this->clientFor($request);

        $existing = SalonFavorite::where('client_id', $client->id)
            ->where('salon_id', $salon->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $favorited = false;
        } else {
            SalonFavorite::create(['client_id' => $client->id, 'salon_id' => $salon->id]);
            $favorited = true;
        }

        return response()->json(['is_favorited' => $favorited]);
    }

    private function clientFor(Request $request): \App\Models\Client
    {
        $client = $request->user()->client;

        if (! $client) {
            throw ValidationException::withMessages([
                'client' => 'Only client accounts can favorite salons.',
            ]);
        }

        return $client;
    }
}
