<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:32px;background:#f4f1fa;font-family:Helvetica,Arial,sans-serif;color:#1C0A3A;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;">
        <h1 style="font-size:20px;margin:0 0 16px;">You're all set, {{ $user->name }}</h1>
        <p style="font-size:14px;line-height:22px;color:#4A1F7C;">
            Your Ripplebox account (phone {{ $user->phone_number }}) is registered and verified.
            You can always sign back in with just your phone number — no password needed.
        </p>
        <a href="{{ config('services.frontend.url') }}" style="display:inline-block;background:#1C0A3A;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 24px;border-radius:10px;margin:16px 0;">
            Open Ripplebox
        </a>
        <p style="font-size:12px;color:#8878a8;margin-top:24px;">
            You're receiving this because you added this email address to your Ripplebox account.
        </p>
    </div>
</body>
</html>
