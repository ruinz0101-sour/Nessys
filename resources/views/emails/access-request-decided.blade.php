<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>ผลการขอสิทธิ์เข้าถึงไฟล์</title>
</head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.6;">
    <h2>ผลการขอสิทธิ์เข้าถึงไฟล์ / โฟลเดอร์</h2>
    <p><strong>ไฟล์/โฟลเดอร์:</strong> {{ $accessRequest->node?->name }}</p>
    <p><strong>สถานะ:</strong> {{ $accessRequest->status->value === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธ' }}</p>
    @if ($accessRequest->granted_permission)
        <p><strong>สิทธิ์ที่ได้รับ:</strong> {{ $accessRequest->granted_permission->value }}</p>
    @endif
    @if ($accessRequest->granted_until)
        <p><strong>ใช้ได้ถึง:</strong> {{ $accessRequest->granted_until->format('Y-m-d H:i') }}</p>
    @endif
    @if ($accessRequest->decision_note)
        <p><strong>หมายเหตุจากผู้พิจารณา:</strong> {{ $accessRequest->decision_note }}</p>
    @endif
</body>
</html>
