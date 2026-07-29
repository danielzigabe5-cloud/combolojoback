<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $appName;

    public function __construct(string $otp)
    {
        $this->otp = $otp;
        $this->appName = 'Compolojo';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔐 Your Compolojo Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otp' => $this->otp,
                'appName' => $this->appName,
            ],
        );
    }
}
