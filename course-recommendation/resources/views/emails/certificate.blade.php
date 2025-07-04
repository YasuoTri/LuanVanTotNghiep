<html>

<body style="text-align: center">
    <h1>CHỨNG CHỈ HOÀN THÀNH KHÓA HỌC</h1>
    <p>Học viên: {{ $student_name }}</p>
    <p>Khóa học: {{ $course_name }}</p>
    <p>Giảng viên: {{ $instructor_name }}</p>
    <p>Ngày cấp: {{ $issued_at }}</p>
    <img src="{{ $signature_url }}" width="150">
</body>

</html>