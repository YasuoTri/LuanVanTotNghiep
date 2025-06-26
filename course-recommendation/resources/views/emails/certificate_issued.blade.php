<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Chứng chỉ đã được cấp</title>
</head>

<body>
    <h2>🎉 Xin chúc mừng {{ $user->name }}!</h2>

    <p>Bạn đã được cấp chứng chỉ cho khóa học <strong>{{ $course->title }}</strong>.</p>

    <p>Bạn có thể tải chứng chỉ của mình tại đường dẫn bên dưới:</p>

    <p>
        <a href="{{ $downloadUrl }}" target="_blank">📄 Tải chứng chỉ</a>
    </p>

    <p>Cảm ơn bạn đã học cùng chúng tôi tại nền tảng Share.</p>

    <p><em>- Hệ thống đào tạo</em></p>
</body>

</html>