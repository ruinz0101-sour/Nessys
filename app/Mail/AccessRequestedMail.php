<?php

namespace App\Mail;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccessRequest $accessRequest,
        public bool $escalatedToAdmin = false,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->escalatedToAdmin ? '[ส่งต่อผู้ดูแลระบบ] ' : '';
        $nodeName = $this->accessRequest->node?->name ?? 'ไฟล์/โฟลเดอร์';

        return new Envelope(
            subject: "{$prefix}คำขอสิทธิ์เข้าถึง: {$nodeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-requested',
        );
    }
}
