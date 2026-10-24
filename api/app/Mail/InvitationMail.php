<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url, public Carbon $expiresAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Հրավեր Phay ադմին վահանակ / Приглашение в Phay');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation');
    }
}
