<h1>Hello {{ $instructor->name }},</h1>
<p>We have successfully sent your payout of ${{ $amountUSD }} via PayPal.</p>
<p>Course: {{ $revenueDistribution->course->title ?? 'N/A' }}</p>
<p>Thank you for teaching with us!</p>