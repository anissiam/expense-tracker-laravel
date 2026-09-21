<?php

namespace App\Mail;

use App\Models\Budget;
use App\Models\BudgetMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PartnerInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Budget $budget,
        public BudgetMember $member,
        public string $inviterName,
        public bool $isNewUser,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isNewUser
                ? "You're invited to share '{$this->budget->name}' — create your account"
                : "You're invited to share '{$this->budget->name}'",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.partner-invite-text',
            with: [
                'budgetName' => $this->budget->name,
                'role' => $this->member->role,
                'inviterName' => $this->inviterName,
                'acceptUrl' => $this->acceptUrl,
                'isNewUser' => $this->isNewUser,
                'expiresAt' => $this->member->expires_at,
            ],
        );
    }
}
