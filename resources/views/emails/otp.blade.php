<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; color: #1A73E8; }
        .otp-code { font-size: 36px; font-weight: bold; color: #1A73E8; text-align: center; padding: 20px; letter-spacing: 10px; background: #f8f9fa; border-radius: 8px; margin: 20px 0; }
        .info { color: #6c757d; font-size: 14px; text-align: center; }
        .footer { margin-top: 30px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header">🔐 OTP Verification</h2>
        <p style="text-align:center; color:#333;">Your one-time password is:</p>
        <div class="otp-code">{{ $otp }}</div>
        <p style="text-align:center; color:#333;">This code will expire in <strong>10 minutes</strong>.</p>
        <hr>
        <p class="info">If you didn't request this code, please ignore this email.</p>
        <div class="footer">Combolojos - OTP Verification</div>
    </div>
</body>
</html>
