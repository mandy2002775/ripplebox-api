<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecipientType;
use App\Enums\RewardType;
use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RewardController extends Controller
{
    /**
     * The authenticated salon's rewards, newest first. The app splits these
     * into Active/Expired tabs client-side rather than as separate endpoints.
     */
    public function index(Request $request): JsonResponse
    {
        $salon = $this->salonFor($request);

        return response()->json(
            $salon->rewards()->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $salon = $this->salonFor($request);

        $data = $request->validate([
            'reward_type' => ['required', Rule::enum(RewardType::class)],
            'reward_value' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'recipient_type' => ['required', Rule::enum(RecipientType::class)],
            'expiry_date' => ['required', 'date', 'after:today'],
        ]);

        $reward = $salon->rewards()->create($data);

        return response()->json($reward, 201);
    }

    /**
     * Edit a reward, or flip is_active to pause/resume it (screen 8's
     * "Pause" button) — same endpoint, since both are partial updates to
     * the same record.
     */
    public function update(Request $request, Reward $reward): JsonResponse
    {
        $this->authorizeOwnership($request, $reward);

        $data = $request->validate([
            'reward_type' => ['sometimes', Rule::enum(RewardType::class)],
            'reward_value' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'string', 'max:255'],
            'recipient_type' => ['sometimes', Rule::enum(RecipientType::class)],
            'expiry_date' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $reward->update($data);

        return response()->json($reward);
    }

    private function salonFor(Request $request): \App\Models\Salon
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile before managing rewards.',
            ]);
        }

        return $salon;
    }

    private function authorizeOwnership(Request $request, Reward $reward): void
    {
        if ($reward->salon_id !== $this->salonFor($request)->id) {
            abort(403, 'This reward does not belong to your business.');
        }
    }
}
