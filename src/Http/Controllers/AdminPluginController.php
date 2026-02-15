<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Services\PluginService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminPluginController extends Controller
{
    protected PluginService $pluginService;

    public function __construct(PluginService $pluginService)
    {
        $this->pluginService = $pluginService;
    }

    /**
     * Display a listing of plugins.
     */
    public function index()
    {
        $plugins = $this->pluginService->getAllPlugins();

        return Inertia::render('Escalated/Admin/Plugins', [
            'plugins' => $plugins,
        ]);
    }

    /**
     * Upload a new plugin.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'plugin' => 'required|file|mimes:zip|max:51200',
        ]);

        try {
            $result = $this->pluginService->uploadPlugin($request->file('plugin'));

            return redirect()->route('escalated.admin.plugins.index')
                ->with('success', 'Plugin uploaded successfully. You can now activate it.');
        } catch (\Exception $e) {
            Log::error('Escalated: Plugin upload failed', [
                'error' => $e->getMessage(),
                'file' => $request->file('plugin')?->getClientOriginalName(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to upload plugin: '.$e->getMessage());
        }
    }

    /**
     * Activate a plugin.
     */
    public function activate(string $slug)
    {
        try {
            $this->pluginService->activatePlugin($slug);

            return redirect()->back()
                ->with('success', 'Plugin activated successfully.');
        } catch (\Exception $e) {
            Log::error('Escalated: Plugin activation failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to activate plugin: '.$e->getMessage());
        }
    }

    /**
     * Deactivate a plugin.
     */
    public function deactivate(string $slug)
    {
        try {
            $this->pluginService->deactivatePlugin($slug);

            return redirect()->back()
                ->with('success', 'Plugin deactivated successfully.');
        } catch (\Exception $e) {
            Log::error('Escalated: Plugin deactivation failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to deactivate plugin: '.$e->getMessage());
        }
    }

    /**
     * Delete a plugin.
     */
    public function destroy(string $slug)
    {
        try {
            // Check if plugin is composer-sourced before attempting delete
            $allPlugins = $this->pluginService->getAllPlugins();
            $plugin = collect($allPlugins)->firstWhere('slug', $slug);

            if ($plugin && $plugin['source'] === 'composer') {
                return redirect()->back()
                    ->with('error', 'Composer plugins cannot be deleted. Remove the package via Composer instead.');
            }

            $this->pluginService->deletePlugin($slug);

            return redirect()->back()
                ->with('success', 'Plugin deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Escalated: Plugin deletion failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete plugin: '.$e->getMessage());
        }
    }
}
