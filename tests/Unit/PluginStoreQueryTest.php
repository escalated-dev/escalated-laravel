<?php

use Escalated\Laravel\Bridge\ContextHandler;

/*
 * ctx.store.query runs on whatever database the host uses, so its filters and
 * ordering must be written for all of them. The suite runs this file on
 * SQLite, MySQL and PostgreSQL.
 */

function pluginStoreHandler(): ContextHandler
{
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme-counters');

    foreach ([
        ['name' => 'alpha', 'count' => 1, 'tier' => 'free', 'meta' => ['region' => 'eu']],
        ['name' => 'bravo', 'count' => 10, 'tier' => 'pro', 'meta' => ['region' => 'us']],
        ['name' => 'charlie', 'count' => 3, 'tier' => 'pro', 'meta' => ['region' => 'eu']],
        ['name' => 'delta', 'count' => 2.5, 'tier' => 'team', 'meta' => ['region' => 'apac']],
    ] as $data) {
        $handler->handle('ctx.store.insert', ['collection' => 'counters', 'data' => $data]);
    }

    return $handler;
}

/**
 * @return list<string>
 */
function pluginStoreQueryNames(ContextHandler $handler, array $filter, array $options = []): array
{
    $rows = $handler->handle('ctx.store.query', [
        'collection' => 'counters',
        'filter' => $filter,
        'options' => $options,
    ]);

    return array_column($rows, 'name');
}

it('filters with $gt and orders by a number as a number', function () {
    expect(pluginStoreQueryNames(pluginStoreHandler(), ['count' => ['$gt' => 1]], ['orderBy' => 'count']))
        ->toBe(['delta', 'charlie', 'bravo']);
});

it('orders descending', function () {
    expect(pluginStoreQueryNames(pluginStoreHandler(), [], ['orderBy' => 'count', 'order' => 'desc']))
        ->toBe(['bravo', 'charlie', 'delta', 'alpha']);
});

it('applies $gte, $lt and $lte', function () {
    $handler = pluginStoreHandler();

    expect(pluginStoreQueryNames($handler, ['count' => ['$gte' => 2.5, '$lt' => 10]], ['orderBy' => 'name']))
        ->toBe(['charlie', 'delta'])
        ->and(pluginStoreQueryNames($handler, ['count' => ['$lte' => 1]]))
        ->toBe(['alpha']);
});

it('applies $ne to strings and numbers', function () {
    $handler = pluginStoreHandler();

    expect(pluginStoreQueryNames($handler, ['tier' => ['$ne' => 'pro']], ['orderBy' => 'name']))
        ->toBe(['alpha', 'delta'])
        ->and(pluginStoreQueryNames($handler, ['count' => ['$ne' => 10]], ['orderBy' => 'name']))
        ->toBe(['alpha', 'charlie', 'delta']);
});

it('applies $in and $nin', function () {
    $handler = pluginStoreHandler();

    expect(pluginStoreQueryNames($handler, ['tier' => ['$in' => ['pro', 'team']]], ['orderBy' => 'name']))
        ->toBe(['bravo', 'charlie', 'delta'])
        ->and(pluginStoreQueryNames($handler, ['count' => ['$nin' => [1, 10]]], ['orderBy' => 'name']))
        ->toBe(['charlie', 'delta']);
});

it('matches plain values, including on a nested path', function () {
    $handler = pluginStoreHandler();

    expect(pluginStoreQueryNames($handler, ['count' => 3]))
        ->toBe(['charlie'])
        ->and(pluginStoreQueryNames($handler, ['meta.region' => 'eu'], ['orderBy' => 'name']))
        ->toBe(['alpha', 'charlie'])
        ->and(pluginStoreQueryNames($handler, ['meta.region' => ['$ne' => 'eu']], ['orderBy' => 'meta.region']))
        ->toBe(['delta', 'bravo']);
});

it('orders strings and applies a limit', function () {
    expect(pluginStoreQueryNames(pluginStoreHandler(), [], ['orderBy' => 'name', 'order' => 'desc', 'limit' => 2]))
        ->toBe(['delta', 'charlie']);
});

it('refuses a field name that is not a plain path', function (array $filter, array $options) {
    expect(fn () => pluginStoreQueryNames(pluginStoreHandler(), $filter, $options))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'operator field' => [["count') OR 1=1 --" => ['$gt' => 0]], []],
    'equality field' => [["tier') OR 1=1 --" => 'pro'], []],
    'order by' => [[], ['orderBy' => 'name desc; drop table x']],
]);
