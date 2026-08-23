<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:32px;background:#f4f1fa;font-family:Helvetica,Arial,sans-serif;color:#1C0A3A;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;">
        <h1 style="font-size:20px;margin:0 0 16px;">You've earned a reward!</h1>
        <p style="font-size:14px;line-height:22px;color:#4A1F7C;">
            Hi {{ $recipient->name }}, {{ $salon->business_name }} just confirmed your referral.
        </p>
        <div style="background:#f4f1fa;border-radius:12px;padding:16px;margin:20px 0;">
            <p style="font-size:16px;font-weight:bold;margin:0 0 4px;">{{ $reward->description }}</p>
            <p style="font-size:13px;color:#8878a8;margin:0;">${{ number_format($reward->reward_value, 2) }} value</p>
        </div>
        <p style="font-size:12px;color:#8878a8;">
            Open the Ripplebox app to see the details.
        </p>
    </div>
</body>
</html>
