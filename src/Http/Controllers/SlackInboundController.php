<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Services\SlackInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackInboundController
{
    public function __invoke(Request $request, string $app, SlackInbox $inbox): JsonResponse
    {
        $result = $inbox->receive($app, $request->getContent(), (string) $request->header('x-slack-request-timestamp', ''),
            (string) $request->header('x-slack-signature', ''));

        return response()->json($result, isset($result['accepted']) ? 202 : 200, ['Cache-Control' => 'no-store']);
    }
}
