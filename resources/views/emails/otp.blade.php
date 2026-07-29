<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #1A237E; font-size: 28px; margin: 0; letter-spacing: 2px;">COMPOLOJO</h1>
            <p style="color: #666; font-size: 14px; margin: 4px 0 0;">Book Sport Venues. Join Games.</p>
        </div>
        <hr style="border: none; border-top: 2px solid #1A237E; opacity: 0.1; margin: 20px 0;">
        <div style="text-align: center; padding: 20px 0;">
            <h2 style="color: #333; font-size: 22px; margin-bottom: 12px;">Email Verification</h2>
            <p style="color: #666; font-size: 16px; margin-bottom: 24px;">
                Use the 6-digit code below to verify your email:
            </p>
            <div style="background-color: #f8f9fc; border: 2px dashed #1A237E; border-radius: 12px; padding: 24px; margin: 20px 0;">
                <div style="font-size: 42px; font-weight: bold; color: #1A237E; letter-spacing: 8px; font-family: monospace;">
                    {{ $otp }}
                </div>
            </div>
            <p style="color: #999; font-size: 13px; margin-top: 16px;">
                ⏱️ This code expires in <strong>10 minutes</strong>
            </p>
        </div>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <div style="text-align: center; padding: 12px 0 0;">
            <p style="color: #999; font-size: 12px; margin: 0;">
                This is an automated message from {{ $appName }}.
            </p>
            <p style="color: #ccc; font-size: 11px; margin: 8px 0 0;">
                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
