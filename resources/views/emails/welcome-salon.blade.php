<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:32px;background:#f4f1fa;font-family:Helvetica,Arial,sans-serif;color:#1C0A3A;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;">
        <h1 style="font-size:20px;margin:0 0 16px;">Welcome to Ripplebox, {{ $salon->business_name }}!</h1>
        <p style="font-size:14px;line-height:22px;color:#4A1F7C;">
            Your 30-day free trial has started. Here's what to do next:
        </p>
        <ul style="font-size:14px;line-height:24px;color:#4A1F7C;padding-left:20px;">
            <li>Finish your business profile so clients can find you</li>
            <li>Create your first referral reward</li>
            <li>Share your dashboard with your team</li>
        </ul>

        <a href="{{ config('services.frontend.url') }}" style="display:inline-block;background:#1C0A3A;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 24px;border-radius:10px;margin:16px 0;">
            Open your dashboard
        </a>

        <p style="font-size:12px;color:#8878a8;margin-top:16px;">
            Get the app:
            <a href="https://apps.apple.com" style="color:#4A1F7C;">App Store</a>
            &middot;
            <a href="https://play.google.com" style="color:#4A1F7C;">Google Play</a>
        </p>

        <p style="font-size:12px;color:#8878a8;margin-top:24px;">
            Questions? Just reply to this email.
        </p>
    </div>
</body>
</html>
