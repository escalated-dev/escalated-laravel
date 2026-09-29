<?php

// Deterministic protocol peer: the runtime repository tests the real Node
// dispatcher separately. This peer exercises the host's actual stdio bridge.
while (($line = fgets(STDIN)) !== false) {
    $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $params = $message['params'] ?? [];
    $result = match ($message['method']) {
        'handshake' => ['compatible' => true, 'protocol_version' => '1.0',
            'http_contract_versions' => in_array('--legacy', $argv, true) ? [] : [1]],
        'manifest' => ['http-fixture' => json_decode(file_get_contents(__DIR__.'/plugin-http-manifest.json'), true)],
        'endpoint', 'webhook' => ['__escalated_http' => 1, 'status' => 401,
            'headers' => ['cache-control' => 'no-store'], 'format' => 'json', 'body' => $params],
        default => ['ok' => true],
    };
    echo json_encode(['jsonrpc' => '2.0', 'id' => $message['id'], 'result' => $result], JSON_THROW_ON_ERROR)."\n";
    fflush(STDOUT);
}
