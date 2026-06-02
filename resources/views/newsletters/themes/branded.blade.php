<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f8fafc; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .container { max-width: 640px; margin: 0 auto; background: #ffffff; }
        .header { padding: 24px; background: {{ $brand['accent'] ?? '#2563eb' }}; color: white; text-align: center; }
        .header img { max-height: 40px; }
        .header h1 { font-size: 20px; margin: 0; }
        .content { padding: 32px 24px; font-size: 16px; line-height: 1.6; }
        .content h1, .content h2 { color: {{ $brand['accent'] ?? '#2563eb' }}; }
        .content p { margin: 0 0 16px; }
        .content a { color: {{ $brand['accent'] ?? '#2563eb' }}; }
        .footer { padding: 16px 24px 32px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer a { color: #64748b; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($brand['logo_url'] ?? null)
                <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] ?? 'Newsletter' }}" />
            @else
                <h1>{{ $brand['name'] ?? 'Newsletter' }}</h1>
            @endif
        </div>
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
