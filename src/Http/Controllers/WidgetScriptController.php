<?php

namespace Escalated\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the standalone widget bundle (resources/dist/widget/escalated-widget.js,
 * built by @escalated-dev/escalated) so any site can load it with a
 * <script> tag pointing at this install.
 */
class WidgetScriptController extends Controller
{
    public const BUNDLE = __DIR__.'/../../../resources/dist/widget/escalated-widget.js';

    public function __invoke(Request $request): Response
    {
        $path = static::BUNDLE;
        abort_unless(is_file($path), 404);

        $etag = '"'.md5_file($path).'"';
        $headers = [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'Access-Control-Allow-Origin' => '*',
        ];

        if (in_array($etag, $request->getETags(), true)) {
            return response('', 304, $headers);
        }

        return response(file_get_contents($path), 200, $headers);
    }
}
