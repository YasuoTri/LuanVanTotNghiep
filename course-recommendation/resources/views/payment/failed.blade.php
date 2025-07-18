<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5;url=http://localhost:4200/my-course">
    <title>Payment Failed</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 1s ease-out;
        }

        @keyframes progress {
            from {
                width: 100%;
            }

            to {
                width: 0;
            }
        }

        .progress-bar {
            height: 4px;
            background: linear-gradient(to right, #dc3545, #e4606d);
            animation: progress 5s linear forwards;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-red-50 to-gray-100 flex items-center justify-center min-h-screen">
    <div class="container bg-white p-8 rounded-xl shadow-2xl max-w-md w-full fade-in">
        <div class="flex justify-center mb-6">
            <svg class="w-16 h-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-red-600 text-center mb-4">Payment Failed</h1>
        <p class="text-gray-600 text-center mb-4">Sorry, there was an issue with your payment. You will be redirected to
            your courses in 5 seconds...</p>
        <div class="progress-bar w-full rounded-full"></div>
        <p class="text-center mt-4 text-sm text-gray-500">If you are not redirected, <a
                href="http://localhost:4200/my-course" class="text-blue-500 hover:underline">click here</a>.</p>
    </div>
</body>

</html>