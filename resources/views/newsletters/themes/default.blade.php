<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f8fafc; color: #0f172a; -webkit-font-smoothing: antialiased; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .content { padding: 32px 24px; font-size: 16px; line-height: 1.6; }
        .content h1 { font-size: 24px; margin: 0 0 16px; }
        .content h2 { font-size: 20px; margin: 24px 0 12px; }
        .content p { margin: 0 0 16px; }
        .content a { color: #2563eb; }
        .footer { padding: 16px 24px 32px; font-size: 12px; color: #64748b; text-align: center; }
        .footer a { color: #64748b; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            {!! $body !!}
        </div>
        <div class="footer">
            <p>
                <a href="{{ $view_in_browser_url }}">View in browser</a>
                ·
                <a href="{{ $unsubscribe_url }}">Unsubscribe</a>
            </p>
            @if($brand['physical_address'] ?? null)
                <p>{{ $brand['physical_address'] }}</p>
            @endif
        </div>
    </div>
</body>
</html>
