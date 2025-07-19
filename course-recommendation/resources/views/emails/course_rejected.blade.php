{{-- <h2>Xin chào {{ $course->instructor->name }}</h2>
<p>Khóa học <strong>{{ $courseName }}</strong> đã bị từ chối.</p>
<p>Lý do: {{ $notes }}</p>
<p>Vui lòng chỉnh sửa lại và gửi yêu cầu duyệt lại.</p>
<p>Trân trọng,</p>
<p>Đội ngũ quản trị</p> --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Course is rejected</title>
    <style>
        :root {
            --primary-color: #d32f2f;
            --primary-light: #f44336;
            --primary-dark: #b71c1c;
            --accent-color: #ff9800;
            --text-color: #333333;
            --light-bg: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
        }

        .email-card {
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transform-style: preserve-3d;
            perspective: 1000px;
            transition: transform 0.5s ease, box-shadow 0.5s ease;
            margin: 20px;
            position: relative;
            border-top: 5px solid var(--primary-color);
        }

        .email-card:hover {
            box-shadow: 0 15px 40px rgba(211, 47, 47, 0.2);
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
            transform: rotate(30deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% {
                transform: rotate(30deg) translate(-30%, -30%);
            }

            100% {
                transform: rotate(30deg) translate(30%, 30%);
            }
        }

        h2 {
            color: white;
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            position: relative;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .content {
            padding: 40px;
            position: relative;
        }

        p {
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.6;
        }

        .rejection-box {
            background-color: rgba(211, 47, 47, 0.08);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .rejection-box:hover {
            transform: translateX(5px);
            box-shadow: 5px 0 15px rgba(211, 47, 47, 0.1);
        }

        .reason-box {
            background-color: #fff8e1;
            border-left: 4px solid var(--accent-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-style: italic;
        }

        strong {
            color: var(--primary-dark);
            font-weight: 600;
        }

        .footer {
            text-align: center;
            padding: 25px;
            background-color: var(--light-bg);
            font-size: 14px;
            color: #666;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .warning-icon {
            display: inline-block;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            border-radius: 50%;
            position: relative;
            margin: 0 auto 25px;
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.3);
            animation: pulse 2s infinite;
        }

        .warning-icon::before,
        .warning-icon::after {
            content: "";
            position: absolute;
            background-color: white;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .warning-icon::before {
            width: 6px;
            height: 30px;
            border-radius: 3px;
        }

        .warning-icon::after {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            top: 70%;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .action-btn {
            display: inline-block;
            background: linear-gradient(to right, var(--primary-color), var(--primary-light));
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 0;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(211, 47, 47, 0.3);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(211, 47, 47, 0.4);
        }
    </style>
</head>

<body>
    <div class="email-card" id="email-card">
        <div class="header">
            <h2>Hello {{ $course->instructors->user->fullname }}</h2>
        </div>

        <div class="content">
            <div class="warning-icon"></div>

            <div class="rejection-box">
                <p>Course: <strong>{{ $course->course_name }}</strong> is rejected.</p>
            </div>

            <div class="reason-box">
                <p><strong>Reason :</strong> {{ $notes }}</p>
            </div>

            <p>Please check and submit again</p>

            <center>
                <a href="#" class="action-btn">Review Course</a>
            </center>

            <p>If you need assistance or have any questions, please feel free to contact our support team.</p>
        </div>

        <div class="footer">
            <p>Sincerely,</p>
            <p><strong>The Admin Team</strong></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const card = document.getElementById('email-card');

            // Hiệu ứng 3D khi di chuột
            card.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
                card.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });

            card.addEventListener('mouseenter', () => {
                card.style.transition = 'all 0.1s ease';
            });

            card.addEventListener('mouseleave', () => {
                card.style.transition = 'all 0.5s ease';
                card.style.transform = 'rotateY(0deg) rotateX(0deg)';
            });

            // Hiệu ứng rung cho warning icon
            setInterval(() => {
                const icon = document.querySelector('.warning-icon');
                icon.style.animation = 'none';
                setTimeout(() => {
                    icon.style.animation = 'pulse 2s infinite';
                }, 10);
            }, 5000);
        });
    </script>
</body>

</html>