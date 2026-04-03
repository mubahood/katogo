<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to users whose subscription is expiring soon.
 *
 * Dispatched via Mail::queue() from the SendExpiryNotifications command so
 * the console command does not block waiting for SMTP delivery.
 */
class SubscriptionExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User         $user,
        public readonly Subscription $subscription,
        public readonly int          $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 1
            ? 'Your Katogo subscription expires tomorrow'
            : "Your Katogo subscription expires in {$this->daysRemaining} days";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-expiry');
    }

    public function attachments(): array
    {
        return [];
    }
}
