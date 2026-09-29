<?php

require __DIR__.'/../../vendor/autoload.php';

use Escalated\Laravel\Escalated;
use Escalated\Laravel\EscalatedServiceProvider;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\SlackInboundEvent;
use Escalated\Laravel\Services\SlackInbox;
use Escalated\Laravel\Services\SlackInboxProcessor;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Foundation\Application;

$fixture = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$factory = new class extends Application
{
    public array $settings = [];

    public string $cachePrefix;

    protected function resolveApplication()
    {
        $app = parent::resolveApplication();
        $app->addAbsoluteCachePathPrefix($this->cachePrefix);

        return $app;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set($this->settings);
    }
};
$factory->settings = $fixture['config'];
$factory->cachePrefix = dirname($argv[1]);
$app = $factory->configure(['extra' => ['providers' => [EscalatedServiceProvider::class], 'dont-discover' => ['*']]])->createApplication();
Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
file_put_contents($argv[1].'.ready'.$argv[2], 'ready');
$deadline = microtime(true) + 10;
while (! is_file($argv[1].'.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent Slack fixture barrier timed out.');
    }
    usleep(10000);
}
if (in_array($fixture['mode'], ['receive', 'contact'], true)) {
    // Both workers must reach creation before either inserts, exposing the
    // MySQL repeatable-read snapshot race in select-then-insert deduplication.
    $model = $fixture['mode'] === 'contact' ? Contact::class : SlackInboundEvent::class;
    $model::creating(function () use ($argv) {
        file_put_contents($argv[1].'.insert-ready'.$argv[2], 'ready');
        $deadline = microtime(true) + 10;
        while (! is_file($argv[1].'.insert-ready1') || ! is_file($argv[1].'.insert-ready2')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrent insert barrier timed out.');
            }
            usleep(10000);
        }
    });
    if ($fixture['mode'] === 'contact') {
        $result = Escalated::db()->transaction(fn () => Contact::findOrCreateByEmail('Concurrent@Example.test', 'Concurrent requester')->id);
    } else {
        $raw = json_encode($fixture['payload'], JSON_THROW_ON_ERROR);
        $time = (string) time();
        $result = app(SlackInbox::class)->receive('default', $raw, $time, 'v0='.hash_hmac('sha256', 'v0:'.$time.':'.$raw, 'fixture-secret'));
    }
} else {
    $result = app(SlackInboxProcessor::class)->process($fixture['id']);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
$app->terminate();
