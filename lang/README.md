# Plugin-shipped translation overrides

This directory holds the **plugin-shipped overrides** layer of the
`escalated` translation namespace.

## Resolution order

Translations under the `escalated` namespace are stitched together at runtime
by `EscalatedServiceProvider::registerTranslations()`. From lowest to highest
precedence:

1. **Central package** — `vendor/escalated-dev/locale/locales/{locale}/...`
   The canonical source of every translation, shared across all Escalated
   host plugins (Laravel, Rails, NestJS, Django, etc.). Maintained in the
   [`escalated-dev/escalated-locale`](https://github.com/escalated-dev/escalated-locale)
   repository.
2. **Plugin-shipped overrides (this directory)** — `lang/vendor/escalated/{locale}/{group}.php`
   and `lang/vendor/escalated/{locale}.json`. Use this for Laravel-specific
   strings that diverge from the central package — e.g. framework-specific
   error wording, command output, or Artisan-only flows.
3. **Host-app overrides** — `{app}/lang/vendor/escalated/{locale}/...` in the
   consuming application. Populated via:
   ```sh
   php artisan vendor:publish --tag=escalated-lang
   ```
   Apps edit those files to brand or localize Escalated for their own
   product.

Each layer is merged on top of the previous one with
`array_replace_recursive` (PHP groups) and `array_merge` (JSON), so a layer
only needs to define the keys it changes.

## File layout

```
lang/
  vendor/
    escalated/
      en/
        messages.php       <- overrides messages.php from central package
        notifications.php
      en.json              <- overrides en.json from central package
      fr/
        ...
```

The `lang/vendor/escalated/` path is Laravel's standard
[published-vendor-translations](https://laravel.com/docs/localization#overriding-package-language-files)
convention. The framework's `Illuminate\Translation\FileLoader` discovers
files there automatically once the parent path is registered on the loader,
which the service provider does in
`registerTranslations()`.

## Don't put new translations here

This directory is for **overrides only**. New translations should land in
[`escalated-dev/escalated-locale`](https://github.com/escalated-dev/escalated-locale)
so every host plugin picks them up — that is the whole point of the
central package.
