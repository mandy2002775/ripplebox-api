<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReferralStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Redemption;
use App\Models\Referral;
use App\Models\Subscription;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ReportsController extends Controller
{
    /**
     * Metrics for screen 10 (Reports). "Cost per lead" is an honest
     * approximation of the prototype's per-reward CPL concept: the
     * prototype computes it as reward value against an assumed service
     * price, but this schema has no per-service price to compute that
     * literally, so this uses (reward value paid out via redemptions in
     * range) / (revenue estimate in range) — both real, derived numbers.
     */
    public function show(Request $request): JsonResponse
    {
        $since = $this->rangeStart($request);
        $referrals = $this->referralsInRange($since);

        return response()->json([
            'referrals_count' => $referrals->count(),
            'conversions_count' => $referrals->where('status', ReferralStatus::Redeemed)->count(),
            'revenue' => round($this->revenueSince($since), 2),
            'cost_per_lead_pct' => $this->costPerLeadPct($since),
            'daily' => $this->dailyBreakdown(),
            'top_salons' => $this->topSalons($since),
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $since = $this->rangeStart($request);
        $rows = $this->referralsInRange($since)->load(['referrer.user', 'referred.user', 'salon']);

        $csv = "id,referrer,referred,salon,status,created_at\n";
        foreach ($rows as $referral) {
            $csv .= implode(',', [
                $referral->id,
                '"'.$referral->referrer->user->name.'"',
                '"'.$referral->referred->user->name.'"',
                '"'.$referral->salon->business_name.'"',
                $referral->status->value,
                $referral->created_at->toDateTimeString(),
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ripplebox-report.csv"',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $since = $this->rangeStart($request);
        $referrals = $this->referralsInRange($since)->load(['referrer.user', 'referred.user', 'salon']);

        $html = view('reports.pdf', [
            'referrals' => $referrals,
            'generatedAt' => now(),
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ripplebox-report.pdf"',
        ]);
    }

    private function rangeStart(Request $request): ?Carbon
    {
        $range = Validator::make($request->query(), [
            'range' => ['sometimes', 'in:7,30,90,all'],
        ])->validate()['range'] ?? '7';

        return $range === 'all' ? null : now()->subDays((int) $range);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Referral>
     */
    private function referralsInRange(?Carbon $since)
    {
        return Referral::when($since, fn ($q) => $q->where('created_at', '>=', $since))->get();
    }

    private function revenueSince(?Carbon $since): float
    {
        return Subscription::where('status', '!=', SubscriptionStatus::Cancelled)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->get()
            ->sum(fn (Subscription $s) => $s->plan_type->monthlyPrice());
    }

    private function costPerLeadPct(?Carbon $since): float
    {
        $referralIds = $this->referralsInRange($since)->pluck('id');

        $rewardsPaid = Redemption::whereIn('referral_id', $referralIds)
            ->with('reward')
            ->get()
            ->sum(fn ($r) => (float) $r->reward->reward_value);

        $revenue = $this->revenueSince($since);

        return $revenue > 0 ? round(($rewardsPaid / $revenue) * 100, 1) : 0.0;
    }

    /**
     * Referrals shared vs converted, per day, for the last 7 days —
     * matches the prototype's chart, which always shows a fixed 7-day
     * window regardless of the selected range.
     */
    private function dailyBreakdown(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        return $days->map(function (Carbon $day) {
            $referrals = Referral::whereBetween('created_at', [$day, $day->copy()->endOfDay()])->get();

            return [
                'date' => $day->toDateString(),
                'shared' => $referrals->count(),
                'converted' => $referrals->where('status', ReferralStatus::Redeemed)->count(),
            ];
        })->values()->all();
    }

    private function topSalons(?Carbon $since): array
    {
        return Referral::when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('salon_id, count(*) as referrals_count')
            ->groupBy('salon_id')
            ->orderByDesc('referrals_count')
            ->with('salon')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'salon_name' => $row->salon->business_name,
                'referrals_count' => $row->referrals_count,
            ])
            ->all();
    }
}
