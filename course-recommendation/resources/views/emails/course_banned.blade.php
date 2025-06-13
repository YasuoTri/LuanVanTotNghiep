<!-- resources/views/emails/course_banned.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Course Banned Notification</title>
</head>

<body style="font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; background-color: #f9f9f9;">
        <h1 style="color: #2c3e50; text-align: center;">Course Banned Notification</h1>
        <p style="font-size: 16px;">Dear Student,</p>
        <p style="font-size: 16px;">We regret to inform you that the course "{{ $course->course_name }}" has been banned
            from our platform. Your payment has been refunded. Please contact <a href="mailto:support@yourplatform.com"
                style="color: #3498db;">support</a> for further assistance.</p>
        <p style="font-size: 16px;">Thank you,</p>
        <p style="font-size: 16px; font-weight: bold;">Course Platform Team</p>
    </div>
</body>

</html>