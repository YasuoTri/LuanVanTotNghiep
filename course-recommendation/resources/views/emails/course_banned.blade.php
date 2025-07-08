{{-- <!-- resources/views/emails/course_banned.blade.php -->
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

</html> --}}
<!DOCTYPE html>
<html>

<head>
    <title>Course Banned Notification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f5f9f5;">
    <div style="max-width: 600px; margin: 20px auto; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <!-- Header with green gradient -->
        <div style="background: linear-gradient(135deg, #2e7d32, #4caf50); padding: 25px 20px; text-align: center;">
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 600;">Course Banned Notification</h1>
        </div>
        
        <!-- Content area -->
        <div style="padding: 30px; background-color: white;">
            <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">Dear Student,</p>
            
            <div style="background-color: #f1f8e9; padding: 15px; border-left: 4px solid #4caf50; margin-bottom: 25px; border-radius: 0 4px 4px 0;">
                <p style="font-size: 16px; line-height: 1.6; margin: 0;">
                    We regret to inform you that the course <strong>"{{ $course->course_name }}"</strong> has been banned from our platform. 
                    Your payment has been refunded.
                </p>
            </div>
            
            <p style="font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
                If you have any questions or need further assistance, please don't hesitate to contact our 
                <a href="mailto:support@yourplatform.com" style="color: #2e7d32; text-decoration: none; font-weight: 500;">support team</a>.
            </p>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ url('/') }}" style="display: inline-block; background-color: #4caf50; color: white; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: 500;">Browse Other Courses</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="background-color: #e8f5e9; padding: 20px; text-align: center; font-size: 14px; color: #2e7d32;">
            <p style="margin: 0;">Thank you for being part of our learning community.</p>
            <p style="margin: 10px 0 0; font-weight: 600;">Course Platform Team</p>
        </div>
    </div>
</body>

</html>