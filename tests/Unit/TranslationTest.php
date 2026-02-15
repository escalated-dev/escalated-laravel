<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

$locales = ['en', 'es', 'fr', 'de'];
$langFiles = ['enums', 'notifications', 'emails', 'messages', 'commands'];

it('has all language files for every locale', function () use ($locales, $langFiles) {
    $langPath = __DIR__.'/../../resources/lang';

    foreach ($locales as $locale) {
        foreach ($langFiles as $file) {
            $filePath = "{$langPath}/{$locale}/{$file}.php";
            expect(file_exists($filePath))
                ->toBeTrue("Missing language file: {$locale}/{$file}.php");
        }
    }
});

it('has identical key sets across all locales', function () use ($locales, $langFiles) {
    $langPath = __DIR__.'/../../resources/lang';

    foreach ($langFiles as $file) {
        $referenceKeys = null;
        $referenceLocale = null;

        foreach ($locales as $locale) {
            $filePath = "{$langPath}/{$locale}/{$file}.php";
            $translations = require $filePath;
            $keys = array_keys(Arr::dot($translations));
            sort($keys);

            if ($referenceKeys === null) {
                $referenceKeys = $keys;
                $referenceLocale = $locale;
                continue;
            }

            $missing = array_diff($referenceKeys, $keys);
            $extra = array_diff($keys, $referenceKeys);

            expect($missing)
                ->toBeEmpty("Missing keys in {$locale}/{$file}.php (present in {$referenceLocale}): ".implode(', ', $missing));

            expect($extra)
                ->toBeEmpty("Extra keys in {$locale}/{$file}.php (not in {$referenceLocale}): ".implode(', ', $extra));
        }
    }
});

it('has no empty translation values', function () use ($locales, $langFiles) {
    $langPath = __DIR__.'/../../resources/lang';

    foreach ($locales as $locale) {
        foreach ($langFiles as $file) {
            $filePath = "{$langPath}/{$locale}/{$file}.php";
            $translations = require $filePath;
            $dotted = Arr::dot($translations);

            foreach ($dotted as $key => $value) {
                expect($value)
                    ->not->toBeEmpty("Empty translation value: {$locale}/{$file}.php → {$key}");
            }
        }
    }
});

it('preserves placeholder patterns across locales', function () use ($locales, $langFiles) {
    $langPath = __DIR__.'/../../resources/lang';

    foreach ($langFiles as $file) {
        $enFilePath = "{$langPath}/en/{$file}.php";
        $enTranslations = Arr::dot(require $enFilePath);

        foreach ($locales as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $localeFilePath = "{$langPath}/{$locale}/{$file}.php";
            $localeTranslations = Arr::dot(require $localeFilePath);

            foreach ($enTranslations as $key => $enValue) {
                preg_match_all('/:(\w+)/', $enValue, $enMatches);
                $enPlaceholders = $enMatches[1];

                if (empty($enPlaceholders)) {
                    continue;
                }

                $localeValue = $localeTranslations[$key] ?? '';
                preg_match_all('/:(\w+)/', $localeValue, $localeMatches);
                $localePlaceholders = $localeMatches[1];

                sort($enPlaceholders);
                sort($localePlaceholders);

                expect($localePlaceholders)
                    ->toBe($enPlaceholders, "Placeholder mismatch in {$locale}/{$file}.php → {$key}");
            }
        }
    }
});
