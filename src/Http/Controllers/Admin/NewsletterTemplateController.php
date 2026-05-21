<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Newsletter\NewsletterTemplate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class NewsletterTemplateController extends Controller
{
    public function __construct(protected EscalatedUiRenderer $ui) {}

    public function index(): mixed
    {
        $templates = NewsletterTemplate::query()->latest()->get();

        return $this->ui->render('Escalated/Admin/Newsletters/Templates/Index', compact('templates'));
    }

    public function create(): mixed
    {
        return $this->ui->render('Escalated/Admin/Newsletters/Templates/Create', ['themes' => $this->themes()]);
    }

    public function store(Request $request): mixed
    {
        $data = $this->validateForm($request);
        NewsletterTemplate::create($data + ['created_by' => Auth::id()]);

        return redirect('/admin/newsletters/templates');
    }

    public function show(NewsletterTemplate $template): mixed
    {
        return $this->ui->render('Escalated/Admin/Newsletters/Templates/Show', [
            'template' => $template,
            'themes' => $this->themes(),
            'isNew' => false,
        ]);
    }

    public function update(NewsletterTemplate $template, Request $request): mixed
    {
        $data = $this->validateForm($request);
        $template->update($data);

        return redirect("/admin/newsletters/templates/{$template->id}");
    }

    public function destroy(NewsletterTemplate $template): mixed
    {
        $template->delete();

        return redirect('/admin/newsletters/templates');
    }

    private function validateForm(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'theme' => 'required|string|max:64',
            'subject_template' => 'nullable|string|max:998',
            'body_markdown' => 'required|string',
            'merge_fields_schema' => 'nullable|array',
        ]);
    }

    private function themes(): array
    {
        $candidates = [
            base_path('vendor/escalated/laravel/resources/views/newsletters/themes'),
            __DIR__.'/../../../../resources/views/newsletters/themes',
            resource_path('views/vendor/escalated/newsletters/themes'),
        ];
        $themes = [];
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                foreach (glob("{$dir}/*.blade.php") ?: [] as $path) {
                    $themes[] = basename($path, '.blade.php');
                }
            }
        }
        $themes = array_values(array_unique($themes));

        return $themes ?: ['default', 'branded'];
    }
}
