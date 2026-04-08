<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\ImportJob;
use Escalated\Laravel\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $importService,
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index()
    {
        $adapters = collect($this->importService->availableAdapters())
            ->map(fn ($a) => [
                'name' => $a->name(),
                'display_name' => $a->displayName(),
                'credential_fields' => $a->credentialFields(),
            ]);

        $jobs = ImportJob::orderByDesc('created_at')->limit(20)->get();

        return $this->renderer->render('Escalated/Admin/Import/Index', [
            'adapters' => $adapters,
            'jobs' => $jobs,
        ]);
    }

    public function connect(string $platform)
    {
        $adapter = $this->importService->resolveAdapter($platform);

        if (! $adapter) {
            abort(404, "Import adapter not found: {$platform}");
        }

        return $this->renderer->render('Escalated/Admin/Import/Connect', [
            'platform' => $platform,
            'display_name' => $adapter->displayName(),
            'credential_fields' => $adapter->credentialFields(),
        ]);
    }

    public function testConnection(Request $request, string $platform)
    {
        $adapter = $this->importService->resolveAdapter($platform);

        if (! $adapter) {
            abort(404);
        }

        $credentials = $request->input('credentials', []);

        try {
            $success = $adapter->testConnection($credentials);

            // Store credentials server-side in session on success (never roundtrip via frontend)
            if ($success) {
                session()->put("import.{$platform}.credentials", encrypt($credentials));
            }

            return response()->json(['success' => $success]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function mapping(Request $request, string $platform)
    {
        $adapter = $this->importService->resolveAdapter($platform);

        if (! $adapter) {
            abort(404);
        }

        // Retrieve credentials from server-side session (not from frontend)
        $credentials = decrypt(session()->get("import.{$platform}.credentials"));

        if (! $credentials) {
            return redirect()->route('escalated.admin.import.connect', $platform)
                ->with('error', 'Credentials expired. Please reconnect.');
        }

        $entityTypes = $adapter->entityTypes();
        $mappings = [];

        foreach ($entityTypes as $type) {
            $mappings[$type] = [
                'defaults' => $adapter->defaultFieldMappings($type),
                'source_fields' => $adapter->availableSourceFields($type, $credentials),
            ];
        }

        return $this->renderer->render('Escalated/Admin/Import/Mapping', [
            'platform' => $platform,
            'display_name' => $adapter->displayName(),
            'entity_types' => $entityTypes,
            'mappings' => $mappings,
        ]);
    }

    public function review(Request $request, string $platform)
    {
        return $this->renderer->render('Escalated/Admin/Import/Review', [
            'platform' => $platform,
            'field_mappings' => $request->input('field_mappings', []),
            'entity_types' => $request->input('entity_types', []),
        ]);
    }

    public function start(Request $request, string $platform)
    {
        $adapter = $this->importService->resolveAdapter($platform);

        if (! $adapter) {
            abort(404);
        }

        // Retrieve credentials from server-side session
        $credentials = decrypt(session()->get("import.{$platform}.credentials"));

        if (! $credentials) {
            return redirect()->route('escalated.admin.import.connect', $platform)
                ->with('error', 'Credentials expired. Please reconnect.');
        }

        $job = ImportJob::create([
            'user_id' => $request->user()->getKey(),
            'platform' => $platform,
            'status' => 'mapping',
            'credentials' => $credentials,
            'field_mappings' => $request->input('field_mappings', []),
        ]);

        // Clear credentials from session now that they're encrypted in the job
        session()->forget("import.{$platform}.credentials");

        // Dispatch the import as a queued job for async execution
        dispatch(function () use ($job) {
            app(ImportService::class)->run($job);
        })->onQueue('imports');

        return redirect()->route('escalated.admin.import.progress', $job->id);
    }

    public function progress(string $jobId)
    {
        $job = ImportJob::findOrFail($jobId);

        return $this->renderer->render('Escalated/Admin/Import/Progress', [
            'job' => $job,
        ]);
    }

    public function status(string $jobId)
    {
        $job = ImportJob::findOrFail($jobId);

        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'error_log' => array_slice($job->error_log ?? [], -50),
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
    }

    public function pause(string $jobId)
    {
        $job = ImportJob::findOrFail($jobId);

        if ($job->status === 'importing') {
            $job->update(['status' => 'paused']);
        }

        return back()->with('success', __('escalated::messages.import.paused'));
    }

    public function resume(string $jobId)
    {
        $job = ImportJob::findOrFail($jobId);

        if ($job->isResumable()) {
            // Transition via state machine, then let run() handle the rest
            if ($job->status === 'failed') {
                $job->transitionTo('mapping');
            }

            dispatch(function () use ($job) {
                app(ImportService::class)->run($job);
            })->onQueue('imports');
        }

        return back()->with('success', __('escalated::messages.import.resumed'));
    }

    public function cancel(string $jobId)
    {
        $job = ImportJob::findOrFail($jobId);
        $job->update(['status' => 'failed', 'completed_at' => now()]);
        $job->purgeCredentials();

        return back()->with('success', __('escalated::messages.import.cancelled'));
    }
}
