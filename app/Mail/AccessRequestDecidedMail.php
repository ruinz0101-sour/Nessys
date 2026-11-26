<?php

namespace App\Mail;

use App\Enums\RequestStatus;
use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessRequestDecidedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AccessRequest $accessRequest,
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = $this->accessRequest->status === RequestStatus::Approved
            ? 'อนุมัติแล้ว'
            : 'ถูกปฏิเสธ';
        $nodeName = $this->accessRequest->node?->name ?? 'ไฟล์/โฟลเดอร์';

        return new Envelope(
            subject: "ผลการขอสิทธิ์เข้าถึง ({$statusLabel}): {$nodeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-request-decided',
        );
    }
}
