{{-- <html>

<body style="text-align: center">
    <h1>CHỨNG CHỈ HOÀN THÀNH KHÓA HỌC</h1>
    <p>Học viên: {{ $student_name }}</p>
    <p>Khóa học: {{ $course_name }}</p>
    <p>Giảng viên: {{ $instructor_name }}</p>
    <p>Ngày cấp: {{ $issued_at }}</p>
    <img src="{{ $signature_url }}" width="150">
</body>

</html> --}}

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Chứng chỉ hoàn thành khóa học</title>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #60ad5e;
            --primary-dark: #1b5e20;
            --gold-color: #ffd700;
            --border-color: rgba(46, 125, 50, 0.3);
            --text-color: #333;
            --shadow-color: rgba(0, 0, 0, 0.2);
        }

        body {
            font-family: 'Times New Roman', serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
        }

        .certificate-3d {
            width: 800px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 50px var(--shadow-color);
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            perspective: 2000px;
            border: 1px solid var(--border-color);
            transition: transform 0.5s ease;
        }

        .certificate-border {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            pointer-events: none;
            z-index: 1;
        }

        .certificate-pattern {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(46, 125, 50, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(46, 125, 50, 0.05) 0%, transparent 20%);
            z-index: 0;
        }

        .certificate-content {
            position: relative;
            z-index: 2;
            padding: 60px;
            text-align: center;
        }

        .certificate-header {
            margin-bottom: 40px;
            position: relative;
        }

        .certificate-header h1 {
            color: var(--primary-dark);
            font-size: 32px;
            margin: 0 0 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            display: inline-block;
        }

        .certificate-header h1::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 25%;
            right: 25%;
            height: 3px;
            background: linear-gradient(to right, transparent, var(--primary-color), transparent);
        }

        .certificate-body {
            margin: 30px 0;
        }

        .certificate-item {
            margin: 25px 0;
            font-size: 18px;
            position: relative;
        }

        .certificate-item strong {
            color: var(--primary-dark);
            font-weight: normal;
            font-size: 20px;
            display: block;
            margin-bottom: 5px;
        }

        .certificate-signature {
            margin: 40px 0 30px;
            position: relative;
        }

        .signature-image {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 10px;
            display: inline-block;
        }

        .signature-title {
            font-style: italic;
            color: var(--primary-dark);
        }

        .certificate-footer {
            margin-top: 50px;
            font-style: italic;
            color: #666;
        }

        .seal {
            position: absolute;
            top: 20px;
            right: 40px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, var(--primary-color) 40%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            box-shadow: 0 5px 15px var(--shadow-color);
            transform: rotate(15deg);
            opacity: 0.9;
        }

        .seal::before {
            content: "";
            position: absolute;
            width: 90%;
            height: 90%;
            border: 2px dashed white;
            border-radius: 50%;
            opacity: 0.7;
        }

        .gold-line {
            position: absolute;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--gold-color), transparent);
            width: 60%;
            left: 20%;
        }

        .gold-line.top {
            top: 0;
        }

        .gold-line.bottom {
            bottom: 0;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>

<body>
    <div class="certificate-3d" id="certificate">
        <div class="certificate-pattern"></div>
        <div class="certificate-border"></div>

        <div class="seal floating">
            <div>Certified<br>Completion</div>
        </div>

        <div class="certificate-content">
            <div class="gold-line top"></div>

            <div class="certificate-header">
                <h1>Chứng chỉ hoàn thành khóa học</h1>
            </div>

            <div class="certificate-body">
                <div class="certificate-item">
                    <strong>Học viên</strong>
                    <span>{{ $student_name }}</span>
                </div>

                <div class="certificate-item">
                    <strong>Khóa học</strong>
                    <span>{{ $course_name }}</span>
                </div>

                <div class="certificate-item">
                    <strong>Giảng viên</strong>
                    <span>{{ $instructor_name }}</span>
                </div>

                <div class="certificate-item">
                    <strong>Ngày cấp</strong>
                    <span>{{ $issued_at }}</span>
                </div>
            </div>

            <div class="certificate-signature">
                <div class="signature-image">
                    <img src="{{ $signature_url }}" width="150" alt="Chữ ký">
                </div>
                <div class="signature-title">Chữ ký xác nhận</div>
            </div>

            <div class="gold-line bottom"></div>
        </div>

        <div class="certificate-footer">
            Chứng chỉ này được cấp để ghi nhận sự hoàn thành khóa học
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const certificate = document.getElementById('certificate');

            // Hiệu ứng 3D khi di chuột
            certificate.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 30;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 30;
                certificate.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;

                // Hiệu ứng ánh sáng
                const glow = document.createElement('div');
                glow.style.position = 'absolute';
                glow.style.width = '100px';
                glow.style.height = '100px';
                glow.style.background = 'radial-gradient(circle, rgba(46, 125, 50, 0.3), transparent 70%)';
                glow.style.borderRadius = '50%';
                glow.style.left = `${e.clientX - 50}px`;
                glow.style.top = `${e.clientY - 50}px`;
                glow.style.pointerEvents = 'none';
                glow.style.zIndex = '1';
                certificate.appendChild(glow);

                setTimeout(() => {
                    glow.style.opacity = '0';
                    glow.style.transition = 'opacity 0.5s';
                    setTimeout(() => glow.remove(), 500);
                }, 100);
            });

            certificate.addEventListener('mouseleave', () => {
                certificate.style.transform = 'rotateY(0deg) rotateX(0deg)';
            });

            // Hiệu ứng hover cho các mục
            const items = document.querySelectorAll('.certificate-item');
            items.forEach(item => {
                item.addEventListener('mouseenter', () => {
                    item.style.transform = 'scale(1.03)';
                    item.style.transition = 'transform 0.3s';
                });
                item.addEventListener('mouseleave', () => {
                    item.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>

</html>