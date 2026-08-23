<?php

namespace App\Mail;

use App\Models\Reward;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RewardEarnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $recipient, public Reward $reward, public Salon $salon)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've earned a reward at {$this->salon->business_name}!",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reward-earned',
        );
    }
}
