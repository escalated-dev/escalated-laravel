<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Illuminate\Support\Facades\View;
use League\CommonMark\CommonMarkConverter;

class NewsletterRendererService
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private CommonMarkConverter $markdown;

    public function __construct(?CommonMarkConverter $markdown = null)
    {
        $this->markdown = $markdown ?? new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function render(NewsletterDelivery $delivery): string
    {
        $newsletter = $delivery->newsletter;
        $contact = $delivery->contact;

        $bodyMarkdown = $newsletter->body_markdown
            ?? ($newsletter->template?->body_markdown ?? '');
        $themeSlug = $newsletter->theme
            ?? ($newsletter->template?->theme ?? config('escalated.newsletters.default_theme', 'default'));

        $bodyHtml = (string) $this->markdown->convert($bodyMarkdown);
        $bodyHtml = $this->resolveMergeFields($bodyHtml, $contact, $delivery);

        $themed = View::make("escalated::newsletters.themes.{$themeSlug}", [
            'subject' => $newsletter->subject,
            'body' => $bodyHtml,
            'unsubscribe_url' => $this->unsubscribeUrl($delivery),
            'view_in_browser_url' => $this->viewInBrowserUrl($delivery),
            'tracking_pixel' => $this->trackingPixelHtml($delivery),
            'brand' => $this->brand(),
        ])->render();

        if (config('escalated.newsletters.tracking_enabled', true)) {
            $themed = $this->rewriteLinks($themed, $delivery);
            $themed = $this->injectPixel($themed, $delivery);
        }

        return $themed;
    }

    private function resolveMergeFields(string $html, $contact, NewsletterDelivery $delivery): string
    {
        $resolver = function (array $m) use ($contact, $delivery): string {
            $path = trim($m[1]);

            return $this->resolvePath($path, $contact, $delivery);
        };

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $resolver, $html);
    }

    private function resolvePath(string $path, $contact, NewsletterDelivery $delivery): string
    {
        $value = match (true) {
            $path === 'contact.name' => (string) ($contact->name ?? ''),
            $path === 'contact.first_name' => $this->firstName((string) ($contact->name ?? '')),
            $path === 'contact.email' => (string) $contact->email,
            $path === 'unsubscribe_url' => $this->unsubscribeUrl($delivery),
            $path === 'view_in_browser_url' => $this->viewInBrowserUrl($delivery),
            str_starts_with($path, 'contact.metadata.') => (string) data_get(
                $contact->metadata ?? [],
                substr($path, strlen('contact.metadata.')),
                '',
            ),
            default => '',
        };

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function firstName(string $full): string
    {
        $parts = explode(' ', trim($full));

        return $parts[0] ?? '';
    }

    public function unsubscribeUrl(NewsletterDelivery $delivery): string
    {
        return url("/escalated/n/u/{$delivery->tracking_token}");
    }

    public function viewInBrowserUrl(NewsletterDelivery $delivery): string
    {
        return url("/escalated/n/v/{$delivery->tracking_token}");
    }

    public function trackingPixelHtml(NewsletterDelivery $delivery): string
    {
        if (! config('escalated.newsletters.tracking_enabled', true)) {
            return '';
        }
        $url = url("/escalated/n/o/{$delivery->tracking_token}.gif");

        return sprintf('<img src="%s" width="1" height="1" alt="" />', e($url));
    }

    private function rewriteLinks(string $html, NewsletterDelivery $delivery): string
    {
        $unsubPrefix = $this->unsubscribeUrl($delivery);
        $viewPrefix = $this->viewInBrowserUrl($delivery);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        foreach ($dom->getElementsByTagName('a') as $node) {
            if (! $node->hasAttribute('href')) {
                continue;
            }
            $href = $node->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }
            $scheme = strtolower(parse_url($href, PHP_URL_SCHEME) ?? '');
            if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
                $node->setAttribute('href', '#');

                continue;
            }
            if (in_array($scheme, ['mailto', 'tel'], true)) {
                continue;
            }
            if (str_starts_with($href, $unsubPrefix) || str_starts_with($href, $viewPrefix)) {
                continue;
            }
            $tracked = url("/escalated/n/c/{$delivery->tracking_token}").'?u='.rtrim(strtr(base64_encode($href), '+/', '-_'), '=');
            $node->setAttribute('href', $tracked);
        }

        $output = $dom->saveHTML();

        return $output !== false ? $output : $html;
    }

    private function injectPixel(string $html, NewsletterDelivery $delivery): string
    {
        $pixel = $this->trackingPixelHtml($delivery);
        if ($pixel === '') {
            return $html;
        }
        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $pixel.'</body>', $html);
        }

        return $html.$pixel;
    }

    private function brand(): array
    {
        return [
            'name' => config('escalated.app_name', config('app.name', 'Support')),
            'accent' => config('escalated.newsletters.brand_accent', '#2563eb'),
            'logo_url' => config('escalated.newsletters.brand_logo_url'),
            'physical_address' => config('escalated.newsletters.brand_physical_address'),
        ];
    }
}
