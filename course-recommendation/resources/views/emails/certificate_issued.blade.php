{{--
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Chứng chỉ đã được cấp</title>
</head>

<body>
    <h2>🎉 Xin chúc mừng {{ $user->fullname }}!</h2>

    <p>Bạn đã được cấp chứng chỉ cho khóa học <strong>{{ $course->title }}</strong>.</p>

    <p>Bạn có thể tải chứng chỉ của mình tại đường dẫn bên dưới:</p>

    <p>
        <a href="{{ $downloadUrl }}" target="_blank">📄 Tải chứng chỉ</a>
    </p>

    <p>Cảm ơn bạn đã học cùng chúng tôi tại nền tảng Share.</p>

    <p><em>- Hệ thống đào tạo</em></p>
</body>

</html> --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Chứng chỉ đã được cấp</title>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #60ad5e;
            --primary-dark: #005005;
            --secondary-color: #f5f5f5;
            --text-color: #333;
            --shadow-color: rgba(0, 0, 0, 0.2);
            --gold-color: #ffd700;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .certificate-container {
            max-width: 600px;
            margin: 20px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 35px var(--shadow-color);
            transform-style: preserve-3d;
            perspective: 1000px;
            position: relative;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }

        .certificate-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .certificate-header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0) 70%);
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

        .certificate-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            position: relative;
            text-shadow: 0 2px 4px var(--shadow-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .certificate-body {
            padding: 30px;
            position: relative;
        }

        .certificate-body::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .decoration {
            position: absolute;
            opacity: 0.1;
            z-index: 0;
        }

        .leaf-1 {
            top: 20px;
            right: 20px;
            width: 80px;
            height: 80px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232e7d32"><path d="M17 8C8 10 5.9 16.8 3 21c1.9.9 4.5 1.5 6 1.5 3.7 0 6.8-2 8.5-4.5C21.3 14 19 10 17 8z"/></svg>');
            transform: rotate(15deg);
        }

        .leaf-2 {
            bottom: 40px;
            left: 30px;
            width: 100px;
            height: 100px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232e7d32"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 18c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8z"/></svg>');
            transform: rotate(-10deg);
        }

        .content {
            position: relative;
            z-index: 1;
        }

        p {
            margin-bottom: 15px;
            position: relative;
            padding-left: 25px;
            font-size: 16px;
        }

        p::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            position: absolute;
            left: 5px;
        }

        strong {
            color: var(--primary-dark);
        }

        .download-btn {
            display: inline-block;
            background: linear-gradient(to right, var(--primary-color), var(--primary-light));
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 30px;
            margin: 20px 0;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .download-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
        }

        .download-btn::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0) 70%);
            transform: rotate(30deg);
            animation: shine 3s infinite;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .download-btn:hover::after {
            opacity: 1;
        }

        .course-title {
            font-size: 22px;
            color: var(--primary-dark);
            margin: 20px 0;
            padding: 15px;
            background-color: rgba(46, 125, 50, 0.1);
            border-left: 4px solid var(--primary-color);
            border-radius: 0 8px 8px 0;
            position: relative;
            overflow: hidden;
        }

        .course-title::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(90deg, rgba(46, 125, 50, 0.1), rgba(255, 255, 255, 0.5), rgba(46, 125, 50, 0.1));
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .footer {
            text-align: center;
            padding: 25px;
            background-color: var(--secondary-color);
            font-size: 14px;
            color: #666;
            font-style: italic;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: var(--gold-color);
            opacity: 0;
        }

        @keyframes confetti-fall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 1;
            }

            100% {
                transform: translateY(1000px) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>

<body>
    <div class="certificate-container" id="certificate">
        <div class="certificate-header">
            <h2>🎉 Xin chúc mừng {{ $user->fullname }}!</h2>
        </div>

        <div class="certificate-body">
            <div class="decoration leaf-1"></div>
            <div class="decoration leaf-2"></div>

            <div class="content">
                <div class="course-title">{{ $course->course_name }}</div>

                <p>Bạn đã được cấp chứng chỉ cho khóa học <strong>{{ $course->course_name }}</strong>.</p>
                <p>Bạn có thể tải chứng chỉ của mình tại đường dẫn bên dưới:</p>

                <a href="{{ $downloadUrl }}" target="_blank" class="download-btn">
                    📄 Tải chứng chỉ
                </a>

                <p>Cảm ơn bạn đã học cùng chúng tôi tại nền tảng Learn Smart.</p>
            </div>
        </div>

        <div class="footer">
            <p>- Hệ thống đào tạo</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('certificate');

            // Hiệu ứng 3D khi di chuột
            container.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 20;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 20;
                container.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });

            container.addEventListener('mouseenter', (e) => {
                container.style.transition = 'all 0.1s ease';
            });

            container.addEventListener('mouseleave', (e) => {
                container.style.transition = 'all 0.5s ease';
                container.style.transform = 'rotateY(0deg) rotateX(0deg)';
            });

            // Hiệu ứng confetti
            function createConfetti() {
                for (let i = 0; i < 50; i++) {
                    setTimeout(() => {
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti';
                        confetti.style.left = Math.random() * 100 + '%';
                        confetti.style.animation = `confetti-fall ${Math.random() * 3 + 2}s linear forwards`;
                        confetti.style.backgroundColor = `hsl(${Math.random() * 60 + 30}, 100%, 50%)`;
                        confetti.style.width = `${Math.random() * 10 + 5}px`;
                        confetti.style.height = `${Math.random() * 10 + 5}px`;
                        container.appendChild(confetti);

                        setTimeout(() => {
                            confetti.remove();
                        }, 5000);
                    }, i * 100);
                }
            }

            // Kích hoạt confetti khi mở email
            setTimeout(createConfetti, 1000);
        });
    </script>
</body>

</html>