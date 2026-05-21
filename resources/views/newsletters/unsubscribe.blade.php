<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Unsubscribe</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 60px auto; padding: 0 20px; color: #0f172a; }
        h1 { font-size: 22px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin-top: 16px; }
        button { background: #dc2626; color: white; border: 0; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-size: 14px; }
    </style>
</head>
<body>
@if($confirmed ?? false)
    <h1>You're unsubscribed</h1>
    <div class="box">
        <p>{{ $email ? $email.' has been removed from marketing newsletters.' : 'You have been unsubscribed.' }}</p>
        <p>You will still receive transactional emails (replies to your support tickets, account notifications, etc.).</p>
    </div>
@else
    <h1>Unsubscribe</h1>
    <div class="box">
        <p>{{ $email ? 'Unsubscribe '.$email.' from marketing newsletters?' : 'Confirm you want to unsubscribe.' }}</p>
        <form method="post">
            @csrf
            <input type="hidden" name="List-Unsubscribe" value="One-Click" />
            <button type="submit">Unsubscribe</button>
        </form>
    </div>
@endif
</body>
</html>
