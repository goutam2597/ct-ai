<?php

namespace App\Mail;

use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Monday 08:00 digest: each brand's mention rate and change, share of
 * voice and answers for the week. Only real changes are called — the body
 * carries the same "no clear change" honesty as the UI.
 */
final class WeeklyDigestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{name: string, mentionRate: float|null, low: float|null, high: float|null, trend: string, shareOfVoice: int|null, answers: int}>  $rows
     */
    public function __construct(
        public readonly Workspace $workspace,
        public readonly array $rows,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Weekly AI visibility digest · '.$this->workspace->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.weekly-digest',
            with: [
                'workspaceName' => $this->workspace->name,
                'rows' => $this->rows,
            ],
        );
    }
}
