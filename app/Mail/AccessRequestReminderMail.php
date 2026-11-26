<?php

namespace App\Mail;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessRequestReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccessRequest $accessRequest,
        public bool $escalatedToAdmin = false,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->escalatedToAdmin ? '[ส่งต่อผู้ดูแลระบบ] ' : '[แจ้งเตือนซ้ำ] ';
        $nodeName = $this->accessRequest->node?->name ?? 'ไฟล์/โฟลเดอร์';

        return new Envelope(
            subject: "{$prefix}คำขอสิทธิ์เข้าถึงที่รอการอนุมัติ: {$nodeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-request-reminder',
        );
    }
}
