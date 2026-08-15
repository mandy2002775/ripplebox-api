<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1C0A3A; }
        h1 { font-size: 16px; color: #1C0A3A; margin-bottom: 2px; }
        p.meta { color: #7A6E8A; margin-top: 0; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #EAE4F2; }
        th { background: #EDE8F9; color: #4A1F7C; }
    </style>
</head>
<body>
    <h1>Ripplebox — Referral Report</h1>
    <p class="meta">Generated {{ $generatedAt->toDayDateTimeString() }}</p>

    <table>
        <thead>
            <tr>
                <th>Referrer</th>
                <th>Referred</th>
                <th>Salon</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($referrals as $referral)
                <tr>
                    <td>{{ $referral->referrer->user->name }}</td>
                    <td>{{ $referral->referred->user->name }}</td>
                    <td>{{ $referral->salon->business_name }}</td>
                    <td>{{ ucfirst($referral->status->value) }}</td>
                    <td>{{ $referral->created_at->toDateString() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No referrals in this range.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
