<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Report Handled</title>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #60ad5e;
            --primary-dark: #005005;
            --secondary-color: #f5f5f5;
            --text-color: #333;
            --shadow-color: rgba(0, 0, 0, 0.2);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px var(--shadow-color);
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .email-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .email-header::before {
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

        .email-header h2 {
            color: white;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            position: relative;
            text-shadow: 0 2px 4px var(--shadow-color);
        }

        .email-body {
            padding: 30px;
            position: relative;
        }

        .email-body::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .leaf-decoration {
            position: absolute;
            width: 100px;
            height: 100px;
            opacity: 0.1;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232e7d32"><path d="M17 8C8 10 5.9 16.8 3 21c1.9.9 4.5 1.5 6 1.5 3.7 0 6.8-2 8.5-4.5C21.3 14 19 10 17 8z"/></svg>');
            background-repeat: no-repeat;
            z-index: 0;
        }

        .leaf-1 {
            top: 20px;
            right: 20px;
            transform: rotate(15deg) scale(1.2);
        }

        .leaf-2 {
            bottom: 40px;
            left: 30px;
            transform: rotate(-10deg) scale(1.5);
        }

        .content {
            position: relative;
            z-index: 1;
        }

        p {
            margin-bottom: 15px;
            position: relative;
            padding-left: 20px;
        }

        p::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        strong {
            color: var(--primary-dark);
        }

        .status-box {
            background-color: var(--secondary-color);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .footer {
            text-align: center;
            padding: 20px;
            background-color: var(--secondary-color);
            font-size: 14px;
            color: #666;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(to right, var(--primary-color), var(--primary-light));
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 0;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
        }

        .course-title {
            font-size: 20px;
            color: var(--primary-dark);
            margin: 15px 0;
            padding: 10px;
            background-color: rgba(46, 125, 50, 0.1);
            border-left: 3px solid var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <h2>Annoucement about Course</h2>
        </div>

        <div class="email-body">
            <div class="leaf-decoration leaf-1"></div>
            <div class="leaf-decoration leaf-2"></div>

            <div class="content">
                <div class="course-title">{{ $course->title }}</div>

                <p>Your is processed after getting reported</p>

                <div class="status-box">
                    <p><strong>Reason:</strong> {{ $report->reason }}</p>
                    <p><strong>Process Status:</strong> {{ ucfirst($status) }}</p>
                    <p><strong>Note from admin:</strong> {{ $adminNotes ?? 'No note' }}</p>
                </div>

                <p>Thank you for co-operating with us to have a great community</p>
            </div>
        </div>

        <div class="footer">
            <p>Sincere<br><strong>Executive team</strong></p>
        </div>
    </div>

    <script>
        // Hiệu ứng 3D khi di chuột
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.querySelector('.email-container');

            container.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
                container.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });

            container.addEventListener('mouseenter', (e) => {
                container.style.transition = 'all 0.1s ease';
            });

            container.addEventListener('mouseleave', (e) => {
                container.style.transition = 'all 0.5s ease';
                container.style.transform = 'rotateY(0deg) rotateX(0deg)';
            });
        });
    </script>
</body>

</html>