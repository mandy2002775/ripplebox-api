<?php

namespace App\Mail;

use App\Models\Salon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeSalonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Salon $salon)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Ripplebox, '.$this->salon->business_name.'!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-salon',
        );
    }
}
