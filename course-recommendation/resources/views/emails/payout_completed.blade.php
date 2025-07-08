<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Payment Successfully Sent</title>
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-light: #42a5f5;
            --primary-dark: #1565c0;
            --success-color: #4caf50;
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
            box-shadow: 0 15px 40px rgba(25, 118, 210, 0.2);
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
            0% { transform: rotate(30deg) translate(-30%, -30%); }
            100% { transform: rotate(30deg) translate(30%, 30%); }
        }

        h1 {
            color: white;
            margin: 0;
            font-size: 28px;
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

        .payment-box {
            background-color: rgba(25, 118, 210, 0.08);
            border-left: 4px solid var(--primary-color);
            padding: 25px;
            margin: 30px 0;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .payment-box:hover {
            transform: translateX(5px);
            box-shadow: 5px 0 15px rgba(25, 118, 210, 0.1);
        }

        .amount {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .payment-method {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
        }

        .course-info {
            background-color: #e8f5e9;
            border-left: 4px solid var(--success-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
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

        .success-icon {
            display: inline-block;
            width: 80px;
            height: 80px;
            background-color: var(--success-color);
            border-radius: 50%;
            position: relative;
            margin: 0 auto 25px;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
            animation: bounce 1.5s infinite;
        }

        .success-icon::before {
            content: "";
            position: absolute;
            left: 25px;
            top: 40px;
            width: 20px;
            height: 10px;
            border-left: 4px solid white;
            border-bottom: 4px solid white;
            transform: rotate(-45deg);
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .paypal-logo {
            width: 100px;
            height: auto;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
    </style>
</head>

<body>
    <div class="email-card" id="email-card">
        <div class="header">
            <h1>Hello {{ $instructor->name }},</h1>
        </div>
        
        <div class="content">
            <div class="success-icon"></div>
            
            <div class="payment-box">
                <p>We have successfully sent your payout via:</p>
                <div class="payment-method">
                    <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal" class="paypal-logo">
                </div>
                <div class="amount">
                    ${{ $amountUSD }}
                </div>
            </div>
            
            <div class="course-info">
                <p><strong>Course:</strong> {{ $revenueDistribution->course->title ?? 'N/A' }}</p>
            </div>
            
            <p style="text-align: center; font-size: 18px;">Thank you for teaching with us!</p>
            
            <p style="text-align: center;">The funds should appear in your PayPal account within 1-3 business days.</p>
        </div>
        
        <div class="footer">
            <p>If you have any questions about this payment, please contact our support team.</p>
            <p><strong>Education Platform Team</strong></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Hiệu ứng rơi tiền
            function createMoneyFalling() {
                const symbols = ['💵', '💰', '💲', '🪙'];
                const cardRect = card.getBoundingClientRect();
                
                for (let i = 0; i < 12; i++) {
                    setTimeout(() => {
                        const money = document.createElement('div');
                        money.textContent = symbols[Math.floor(Math.random() * symbols.length)];
                        money.style.position = 'absolute';
                        money.style.left = Math.random() * 80 + 10 + '%';
                        money.style.top = '-30px';
                        money.style.fontSize = (Math.random() * 15 + 20) + 'px';
                        money.style.opacity = Math.random() * 0.5 + 0.5;
                        money.style.animation = `fall ${Math.random() * 2 + 1}s linear forwards`;
                        money.style.zIndex = '0';
                        money.style.transform = `rotate(${Math.random() * 360}deg)`;
                        
                        card.appendChild(money);
                        
                        setTimeout(() => {
                            money.remove();
                        }, 3000);
                    }, i * 200);
                }
            }
            
            // Thêm animation cho tiền rơi
            const style = document.createElement('style');
            style.innerHTML = `
                @keyframes fall {
                    to {
                        transform: translateY(${window.innerHeight + 100}px) rotate(360deg);
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Kích hoạt hiệu ứng tiền rơi
            setTimeout(createMoneyFalling, 1000);
            setInterval(createMoneyFalling, 4000);
        });
    </script>
</body>

</html>