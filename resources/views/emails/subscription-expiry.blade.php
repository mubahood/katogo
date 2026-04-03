<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expiry Notice</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background-color: #e74c3c; padding: 30px; text-align: center; color: #fff; }
        .header h1 { margin: 0; font-size: 24px; }
        .body { padding: 30px; color: #333; }
        .body p { line-height: 1.6; }
        .details { background: #f9f9f9; border-left: 4px solid #e74c3c; padding: 15px 20px; margin: 20px 0; border-radius: 4px; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 6px 0; }
        .details td:first-child { color: #666; width: 140px; }
        .cta { text-align: center; margin: 30px 0; }
        .cta a { background-color: #e74c3c; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-size: 16px; }
        .footer { padding: 20px 30px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Katogo — Subscription Expiry Notice</h1>
    </div>
    <div class="body">
        <p>Dear <strong>{{ $user->name }}</strong>,</p>

        @if ($daysRemaining === 1)
            <p>Your <strong>{{ $subscription->plan->name }}</strong> subscription on Katogo expires <strong>tomorrow</strong>.</p>
        @else
            <p>Your <strong>{{ $subscription->plan->name }}</strong> subscription on Katogo expires in <strong>{{ $daysRemaining }} days</strong>.</p>
        @endif

        <p>To continue enjoying unlimited access to our movies and series, please renew your subscription before it expires.</p>

        <div class="details">
            <table>
                <tr>
                    <td>Plan</td>
                    <td><strong>{{ $subscription->plan->name }}</strong></td>
                </tr>
                <tr>
                    <td>Expiry Date</td>
                    <td><strong>{{ $subscription->getFormattedEndDate() }}</strong></td>
                </tr>
                <tr>
                    <td>Days Remaining</td>
                    <td><strong>{{ $daysRemaining }} day{{ $daysRemaining > 1 ? 's' : '' }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="cta">
            <a href="{{ url('/') }}">Renew My Subscription</a>
        </div>

        <p>If you have any questions, contact us via WhatsApp: <strong>+256 700 123456</strong>.</p>
        <p>Thank you for being a Katogo subscriber!</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Katogo. All rights reserved.
    </div>
</div>
</body>
</html>
