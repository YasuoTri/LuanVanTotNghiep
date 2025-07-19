{{-- <h2>Xin chào {{ $course->instructor->name }}</h2>
<p>Khóa học <strong>{{ $courseName }}</strong> đã được admin phê duyệt và sẽ sớm hiển thị trở lại cho học viên.</p>
<p>Trân trọng,</p>
<p>Đội ngũ quản trị</p> --}}

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Khóa học đã được phê duyệt</title>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #60ad5e;
            --primary-dark: #1b5e20;
            --accent-color: #ffd700;
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
            box-shadow: 0 15px 40px rgba(46, 125, 50, 0.2);
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

        .highlight-box {
            background-color: rgba(46, 125, 50, 0.08);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .highlight-box:hover {
            transform: translateX(5px);
            box-shadow: 5px 0 15px rgba(46, 125, 50, 0.1);
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

        .checkmark {
            display: inline-block;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            border-radius: 50%;
            position: relative;
            margin: 0 auto 25px;
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
            animation: bounce 2s infinite;
        }

        .checkmark::after {
            content: "";
            position: absolute;
            left: 20px;
            top: 30px;
            width: 20px;
            height: 10px;
            border-left: 3px solid white;
            border-bottom: 3px solid white;
            transform: rotate(-45deg);
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .leaf-decoration {
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
    </style>
</head>

<body>
    <div class="email-card" id="email-card">
        <div class="leaf-decoration leaf-1"></div>
        <div class="leaf-decoration leaf-2"></div>

        <div class="header">
            <h2>Hello {{ $course->instructors->user->fullname }}</h2>
        </div>

        <div class="content">
            <div class="checkmark"></div>

            <div class="highlight-box">
                <p>Your course <strong>{{ $course->course_name }}</strong> has been approved by the admin and will soo
                    n be visible again to learners.</p>
            </div>

            <p>Thank you for your patience during the review process. Your course is now accessible to all learner
                s on the platform.</p>
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

            // Hiệu ứng lá rơi
            function createFallingLeaves() {
                const leaves = ['🍀', '🌿', '🍃', '🍂'];
                const cardRect = card.getBoundingClientRect();

                for (let i = 0; i < 8; i++) {
                    setTimeout(() => {
                        const leaf = document.createElement('div');
                        leaf.textContent = leaves[Math.floor(Math.random() * leaves.length)];
                        leaf.style.position = 'absolute';
                        leaf.style.left = Math.random() * 80 + 10 + '%';
                        leaf.style.top = '-30px';
                        leaf.style.fontSize = (Math.random() * 10 + 15) + 'px';
                        leaf.style.opacity = Math.random() * 0.5 + 0.3;
                        leaf.style.animation = `fall ${Math.random() * 3 + 2}s linear forwards`;
                        leaf.style.zIndex = '0';

                        card.appendChild(leaf);

                        setTimeout(() => {
                            leaf.remove();
                        }, 5000);
                    }, i * 300);
                }
            }

            // Thêm animation cho lá rơi
            const style = document.createElement('style');
            style.innerHTML = `
                @keyframes fall {
                    to {
                        transform: translateY(${window.innerHeight + 100}px) rotate(360deg);
                    }
                }
            `;
            document.head.appendChild(style);

            // Kích hoạt hiệu ứng lá rơi
            setTimeout(createFallingLeaves, 1000);
            setInterval(createFallingLeaves, 5000);
        });
    </script>
</body>

</html>