<?php

// A stand-in plugin runtime with two SDK plugins. Like the real runtime it
// loads every installed plugin whatever the host thinks of them, so it logs
// each message the host sends (to the file named by argv[1]) for the tests
// to see what the host chose to send. On `action` it calls `ctx.config.set`
// back for each plugin, the way a plugin's handler would, and logs the answer.
$log = $argv[1];
$plugin = fn (string $name, string $label) => [
    'name' => $name,
    'version' => '1.2.3',
    'description' => "{$label} plugin",
    'pages' => [['route' => 'settings', 'component' => 'Settings', 'menu' => ['label' => $label]]],
    'widgets' => [['component' => 'Inbox', 'label' => "{$label} inbox", 'hasBadge' => true]],
    'components' => [['page' => 'ticket.show', 'slot' => 'sidebar', 'component' => 'Panel']],
    'endpoints' => [['method' => 'GET', 'path' => '/ping']],
    'webhooks' => [['method' => 'POST', 'path' => '/hook']],
    'cronSchedules' => ['* * * * *'],
];
$manifests = [
    '@acme/plugin-crm' => $plugin('@acme/plugin-crm', 'CRM'),
    '@acme/plugin-quiet' => $plugin('@acme/plugin-quiet', 'Quiet'),
];

$write = function (array $message) {
    echo json_encode($message, JSON_THROW_ON_ERROR)."\n";
    fflush(STDOUT);
};
$record = fn (array $entry) => file_put_contents($log, json_encode($entry)."\n", FILE_APPEND | LOCK_EX);
$nextId = 1000;

$hostCall = function (string $method, array $params) use ($write, &$nextId) {
    $id = $nextId++;
    $write(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    while (($line = fgets(STDIN)) !== false) {
        $message = json_decode($line, true);
        if (($message['id'] ?? null) === $id && ! isset($message['method'])) {
            return isset($message['error']) ? ['error' => $message['error']['message'] ?? 'error'] : ['result' => $message['result'] ?? null];
        }
    }

    return ['error' => 'closed'];
};

while (($line = fgets(STDIN)) !== false) {
    $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $method = $message['method'];
    $params = $message['params'] ?? [];

    if ($method !== 'handshake' && $method !== 'manifest') {
        $record(['method' => $method, 'params' => $params]);
    }

    if ($method === 'action' && ($params['hook'] ?? '') === 'test.ctx') {
        foreach (array_keys($manifests) as $name) {
            $answer = $hostCall('ctx.config.set', ['plugin' => $name, 'data' => ['seen' => true]]);
            $record(['method' => 'ctx-answer', 'plugin' => $name, 'answer' => $answer]);
        }
    }

    $result = match ($method) {
        'handshake' => ['compatible' => true, 'protocol_version' => '1.0', 'http_contract_versions' => [1]],
        'manifest' => array_values($manifests),
        'filter' => $params['value'],
        'endpoint', 'webhook' => ['plugin' => $params['plugin'], 'path' => $params['path']],
        'badge' => 5,
        default => ['ok' => true],
    };
    $write(['jsonrpc' => '2.0', 'id' => $message['id'], 'result' => $result]);
}
