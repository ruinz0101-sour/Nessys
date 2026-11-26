<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>แจ้งเตือนซ้ำ: คำขอสิทธิ์เข้าถึงไฟล์</title>
</head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.6;">
    <h2>แจ้งเตือนคำขอสิทธิ์เข้าถึงที่ยังไม่ได้รับการตอบกลับ</h2>
    @if ($escalatedToAdmin)
        <p style="color: #b45309; font-weight: bold;">
            เจ้าของไฟล์ถูกปิดบัญชี ระบบจึงส่งต่อคำขอนี้ให้ผู้ดูแลระบบ (Admin) พิจารณา
        </p>
    @endif
    <p><strong>ผู้ขอสิทธิ์:</strong> {{ $accessRequest->requester?->name }} ({{ $accessRequest->requester?->email }})</p>
    <p><strong>ไฟล์/โฟลเดอร์:</strong> {{ $accessRequest->node?->name }}</p>
    <p><strong>เหตุผล:</strong> {{ $accessRequest->reason ?: '-' }}</p>
    <p>
        <a href="{{ url('/access-requests') }}" style="display: inline-block; padding: 10px 16px; background: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 6px;">
            พิจารณาคำขอ
        </a>
    </p>
</body>
</html>
