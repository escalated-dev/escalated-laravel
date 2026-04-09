<?php

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->service = app(ReportExportService::class);
});

it('exports tickets report as CSV', function () {
    Ticket::factory()->count(3)->create([
        'created_at' => now()->subDay(),
    ]);

    $response = $this->service->exportToCsv('tickets', ['period' => 7]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('tickets_report_');
});

it('exports tickets report as JSON', function () {
    Ticket::factory()->count(2)->create([
        'created_at' => now()->subDay(),
    ]);

    $response = $this->service->exportToJson('tickets', ['period' => 7]);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);

    expect($data)->toHaveKeys(['report_type', 'generated_at', 'filters', 'record_count', 'data'])
        ->and($data['report_type'])->toBe('tickets');
});

it('exports agent performance report as JSON', function () {
    $agent = $this->createAgent();

    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHour(),
        'resolved_at' => now(),
    ]);

    $response = $this->service->exportToJson('agent_performance', ['period' => 7]);
    $data = $response->getData(true);

    expect($data['report_type'])->toBe('agent_performance')
        ->and($data['data'])->toBeArray();
});

it('exports SLA compliance report', function () {
    $response = $this->service->exportToJson('sla_compliance', ['period' => 30]);
    $data = $response->getData(true);

    expect($data['report_type'])->toBe('sla_compliance');
});

it('exports CSAT report', function () {
    $response = $this->service->exportToJson('csat', ['period' => 30]);
    $data = $response->getData(true);

    expect($data['report_type'])->toBe('csat');
});

it('exports resolution times report', function () {
    $response = $this->service->exportToJson('resolution_times', ['period' => 30]);
    $data = $response->getData(true);

    expect($data['report_type'])->toBe('resolution_times');
});

it('exports first response times report', function () {
    $response = $this->service->exportToJson('first_response_times', ['period' => 30]);
    $data = $response->getData(true);

    expect($data['report_type'])->toBe('first_response_times');
});

it('throws exception for unsupported report type', function () {
    $this->service->exportToJson('invalid_type', []);
})->throws(InvalidArgumentException::class, 'Unsupported report type: invalid_type');

it('returns supported types', function () {
    $types = ReportExportService::supportedTypes();

    expect($types)->toContain(
        'tickets',
        'agent_performance',
        'sla_compliance',
        'csat',
        'resolution_times',
        'first_response_times',
    );
});

it('generates CSV with correct structure', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(4),
    ]);

    $response = $this->service->exportToCsv('resolution_times', ['period' => 7]);

    $output = $response->getContent();

    expect($output)->toContain('bucket')
        ->and($output)->toContain('count')
        ->and($output)->toContain('percentage');
});

it('defaults to 30 day period when no period filter given', function () {
    $response = $this->service->exportToJson('tickets', []);
    $data = $response->getData(true);

    expect($data['filters'])->toBe([]);
});
