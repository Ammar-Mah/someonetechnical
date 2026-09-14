<?php

/**
 * Global helpers. Everything here is available in components, handlers, views
 * and templates without importing anything.
 */

// -----------------------------------------------------------------------------
// Assets
// -----------------------------------------------------------------------------

/**
 * URL for a file in public/, carrying a version that changes when the file does.
 *
 * The version is the file's modification time: an unchanged file keeps the same
 * URL and stays cached, and editing it makes every browser fetch it exactly
 * once. That is one stat() per asset, which the filesystem cache answers.
 *
 * $cached = false appends time() instead, producing a different URL on every
 * request. Only for a file written during the request itself.
 */
function asset(string $path, bool $cached = true): string
{
    $base = defined('APP_URL') ? APP_URL : '/';

    if (!$cached) {
        return $base . $path . '?v=' . time();
    }

    $file = ROOT . '/public/' . ltrim($path, '/');
    // A missing file is not this function's problem to report — emit the plain
    // URL and let the 404 say so, rather than raising a warning here (which the
    // global error handler would turn into a fatal).
    $stamp = is_file($file) ? filemtime($file) : false;

    return $base . $path . ($stamp === false ? '' : '?v=' . $stamp);
}

// -----------------------------------------------------------------------------
// Escaping
// -----------------------------------------------------------------------------

/**
 * HTML-escape a value.
 *
 * Templates and component attributes escape for you. Reach for this when you
 * are assembling a markup STRING by hand — inside a raw() block — where nothing
 * else can know which parts are data.
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Mark a string as markup, so a template prints it instead of escaping it.
 *
 *     $this->icon = raw('<svg …></svg>');
 *
 * Everything a template prints is escaped unless it is a Component (markup by
 * construction) or wrapped like this. If you find yourself reaching for raw()
 * around something that came from a user, that is the signal to build it out of
 * components instead — or to e() the data parts first.
 */
function raw(string|Stringable|null $html): Html
{
    return new Html((string)($html ?? ''));
}

// -----------------------------------------------------------------------------
// Translation
// -----------------------------------------------------------------------------

/**
 * Translate a key.
 *
 * Keys ARE the English strings, matched case-insensitively, so English needs no
 * file and a missing translation degrades to readable English rather than to a
 * placeholder like "items.title.header".
 */
function trans(string $key): string
{
    $translations = function_exists('app_data') ? (app_data('translations') ?? []) : [];
    return $translations[strtolower($key)] ?? $key;
}

/** Alias of trans(), for templates where the shorter name reads better. */
function __(string $key): string
{
    return trans($key);
}

/** Load and memoise src/app/Translations/<lang>.json. */
function getTranslation(string $lang): array
{
    static $loaded = [];

    $lang = preg_replace('/[^a-z0-9_-]/i', '', $lang);
    if ($lang === '') {
        return [];
    }
    if (isset($loaded[$lang])) {
        return $loaded[$lang];
    }

    $filePath = SRC . '/app/Translations/' . $lang . '.json';
    if (!is_file($filePath)) {
        return $loaded[$lang] = [];
    }

    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return $loaded[$lang] = [];
    }

    $decoded = json_decode($raw, true);
    return $loaded[$lang] = is_array($decoded) ? $decoded : [];
}

/**
 * Languages the app can actually display, keyed by code.
 *
 * Derived from the files that exist rather than from a hardcoded list, so
 * adding a language is adding its file. English is always offered because the
 * translation keys are the English strings.
 */
function availableLanguages(): array
{
    static $langs = null;
    if ($langs !== null) return $langs;

    $names = [
        'en' => 'English',    'de' => 'Deutsch',   'fr' => 'Français',  'es' => 'Español',
        'it' => 'Italiano',   'pt' => 'Português', 'nl' => 'Nederlands','pl' => 'Polski',
        'da' => 'Dansk',      'sv' => 'Svenska',   'tr' => 'Türkçe',    'ru' => 'Русский',
        'ar' => 'العربية',      'ur' => 'اردو',        'fa' => 'فارسی',      'he' => 'עברית',
        'hi' => 'हिन्दी',        'ml' => 'മലയാളം',      'zh' => '中文',       'ja' => '日本語',
    ];

    $langs = ['en' => $names['en']];
    foreach (glob(SRC . '/app/Translations/*.json') ?: [] as $file) {
        $code = basename($file, '.json');
        if ($code === 'en' || !preg_match('/^[a-z]{2}$/', $code)) continue;
        $langs[$code] = $names[$code] ?? strtoupper($code);
    }
    return $langs;
}

/** The selected language code, guaranteed to be one that can be displayed. */
function currentLanguage(): string
{
    $lang = (string)(State::get('language', '') ?? '');
    if (isset(availableLanguages()[$lang])) return $lang;

    $default = defined('APP_LOCALE') ? (string)APP_LOCALE : 'en';
    return isset(availableLanguages()[$default]) ? $default : 'en';
}

/** Text direction for a language code. */
function languageDirection(?string $lang = null): string
{
    $lang = $lang ?? currentLanguage();
    return in_array($lang, ['ar', 'ur', 'fa', 'he'], true) ? 'rtl' : 'ltr';
}

// -----------------------------------------------------------------------------
// Presentation
// -----------------------------------------------------------------------------

/** 'light' or 'dark'. Drives the data-theme attribute on <html>. */
function currentTheme(): string
{
    return State::get('theme', 'light') === 'dark' ? 'dark' : 'light';
}

/**
 * Black or white ink on a given background, whichever is actually readable.
 *
 * Not the mean of R, G and B: that treats the channels as equally bright, and
 * they are not — the eye takes about 71% of its brightness from green and 7%
 * from blue, so mid-greens get judged far too dark and handed white text they
 * cannot carry. This computes WCAG relative luminance and returns whichever
 * ink has the higher contrast ratio.
 *
 * Accepts '#rgb', '#rrggbb' or an hsl()/named colour (which it cannot measure,
 * so those fall back to white — pass hex when the contrast matters).
 */
function readableInk(string $background): string
{
    $hex = ltrim(trim($background), '#');

    if (!preg_match('/^[0-9a-f]{3}$|^[0-9a-f]{6}$/i', $hex)) {
        return '#ffffff';
    }
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    [$red, $green, $blue] = sscanf('#' . $hex, '#%02x%02x%02x');

    $channel = static function ($value) {
        $value /= 255;
        return $value <= 0.03928 ? $value / 12.92 : pow(($value + 0.055) / 1.055, 2.4);
    };
    $luminance = 0.2126 * $channel($red) + 0.7152 * $channel($green) + 0.0722 * $channel($blue);

    $onWhite = 1.05 / ($luminance + 0.05);   // contrast against white
    $onBlack = ($luminance + 0.05) / 0.05;   // contrast against black

    return $onBlack >= $onWhite ? '#000000' : '#ffffff';
}

/** "3 days ago". Returns 'Just now' for anything under a second. */
function humanReadableTime($dateTimeString): string
{
    $timestamp = strtotime((string)$dateTimeString);
    if ($timestamp === false) return '';

    $difference = time() - $timestamp;
    if ($difference < 1) return trans('Just now');

    $units = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60      => 'month',
        24 * 60 * 60           => 'day',
        60 * 60                => 'hour',
        60                     => 'minute',
        1                      => 'second',
    ];

    foreach ($units as $seconds => $name) {
        if ($difference >= $seconds) {
            $count = (int)floor($difference / $seconds);
            return $count . ' ' . trans($name . ($count > 1 ? 's' : '')) . ' ' . trans('ago');
        }
    }
    return trans('Just now');
}

/** Shorten text for a preview, without cutting a multi-byte character in half. */
function excerpt(string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
    return mb_strlen($text, 'UTF-8') > $limit
        ? mb_substr($text, 0, $limit, 'UTF-8') . '…'
        : $text;
}

// -----------------------------------------------------------------------------
// Debugging
// -----------------------------------------------------------------------------

/** Dump values in a readable block. */
function d(...$args): void
{
    echo '<pre style="position:relative;z-index:9999;padding:12px;border:1px solid #ddd;'
       . 'border-radius:4px;background:#f8f9fa;color:#111;text-align:left">';
    foreach ($args as $arg) {
        var_dump($arg);
    }
    echo '</pre>';
}

/** Dump and stop. */
function dd(...$args): void
{
    d(...$args);
    die(1);
}
