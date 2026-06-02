<?php

namespace Escalated\Laravel\Http\Controllers\Public;

use Escalated\Laravel\Services\Newsletter\NewsletterTrackerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class NewsletterTrackingController extends Controller
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const PIXEL_BYTES = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xfc\xff\xff?\x03\x00\x05\xfe\x02\xfe\xdc\xccY\xe7\x00\x00\x00\x00IEND\xaeB`\x82";

    public function __construct(private readonly NewsletterTrackerService $tracker) {}

    public function open(string $token): Response
    {
        $clean = $this->stripExt($token);
        $this->tracker->recordOpen($clean);

        return response(self::PIXEL_BYTES, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function click(string $token, Request $request): Response
    {
        $encoded = (string) $request->query('u', '');
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false) {
            return response('Bad request', 400);
        }
        $scheme = strtolower(parse_url($decoded, PHP_URL_SCHEME) ?? '');
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return response('Bad request', 400);
        }
        $this->tracker->recordClick($token, $decoded);

        return redirect()->away($decoded, 302);
    }

    private function stripExt(string $token): string
    {
        return preg_replace('/\.(gif|png|jpg)$/i', '', $token);
    }
}
