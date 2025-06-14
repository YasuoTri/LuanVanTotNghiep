<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Yêu cầu giảng viên bị từ chối</title>
</head>
<body>
    <h2>Xin chào {{ $user->username }},</h2>

    <p>Chúng tôi rất tiếc phải thông báo rằng yêu cầu trở thành giảng viên của bạn đã bị <strong>từ chối</strong>.</p>

    @if ($adminNotes)
        <p><strong>Lý do:</strong> {{ $adminNotes }}</p>
    @endif

    <p>Bạn có thể cập nhật hồ sơ và nộp lại trong tương lai.</p>

    <p>Trân trọng,<br>Đội ngũ quản trị</p>
</body>
</html>
