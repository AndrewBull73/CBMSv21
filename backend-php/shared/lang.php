<?php
declare(strict_types=1);

namespace App\Shared;

use App\Shared\SessionHelper;

class Lang
{
    private static string $defaultLang = 'en';
    private static array $cache = [];
    private static array $reverseLiteralCache = [];
    private static array $patternCache = [];
    private const LANGUAGE_LABELS = [
        'en' => 'English',
        'fr' => 'Francais',
        'es' => 'Espanol',
    ];

    public static function init(): void
    {
        self::getActiveLang();
    }

    public static function getDefaultLang(): string
    {
        $default = self::normalize(self::$defaultLang);
        if ($default !== '' && self::hasLanguage($default)) {
            return $default;
        }

        $available = self::availableLanguages();
        if ($available !== []) {
            return $available[0];
        }

        return 'en';
    }

    public static function setDefaultLang(string $lang): bool
    {
        $normalized = self::normalize($lang);
        if ($normalized === '' || !self::hasLanguage($normalized)) {
            return false;
        }

        self::$defaultLang = $normalized;
        self::$reverseLiteralCache = [];
        self::$patternCache = [];
        return true;
    }

    /**
     * Get the currently active language from session (or fallback).
     */
    public static function getActiveLang(): string
    {
        $lang = self::normalize((string) SessionHelper::get('lang', self::getDefaultLang()));
        if ($lang === '' || !self::hasLanguage($lang)) {
            $lang = self::getDefaultLang();
            SessionHelper::set('lang', $lang);
        }

        return $lang;
    }

    /**
     * Set the active language, if translation file exists.
     */
    public static function setActiveLang(string $lang): bool
    {
        $normalized = self::normalize($lang);
        if ($normalized === '' || !self::hasLanguage($normalized)) {
            return false;
        }

        SessionHelper::set('lang', $normalized);
        return true;
    }

    /**
     * Translate a key using the active language, falling back to default.
     */
    public static function t(string $key, array $replacements = []): string
    {
        $lang = self::getActiveLang();

        // Load into cache once per language
        self::loadLanguage($lang);
        self::loadLanguage(self::getDefaultLang());

        $value = self::$cache[$lang][$key]
            ?? self::$cache[self::getDefaultLang()][$key]
            ?? self::translateLiteral($key, $lang);

        foreach ($replacements as $k => $v) {
            $value = str_replace(':' . $k, (string)$v, $value);
        }
        return $value;
    }

    /**
     * Return available languages (based on lang/*.php files).
     */
    public static function availableLanguages(): array
    {
        $files = glob(__DIR__ . '/../lang/*.php');
        $codes = array_map(
            static fn(string $file): string => self::normalize(basename($file, '.php')),
            $files ?: []
        );
        $codes = array_values(array_unique(array_filter($codes, static fn(string $code): bool => $code !== '')));
        sort($codes);

        return $codes;
    }

    public static function hasLanguage(string $lang): bool
    {
        return in_array(self::normalize($lang), self::availableLanguages(), true);
    }

    public static function getLanguageLabel(string $lang): string
    {
        $normalized = self::normalize($lang);
        if ($normalized === '') {
            return '';
        }

        return self::LANGUAGE_LABELS[$normalized] ?? strtoupper($normalized);
    }

    public static function availableLanguageLabels(?array $languageCodes = null): array
    {
        $codes = $languageCodes ?? self::availableLanguages();
        $labels = [];
        foreach ($codes as $code) {
            $normalized = self::normalize((string) $code);
            if ($normalized === '') {
                continue;
            }

            $labels[$normalized] = self::getLanguageLabel($normalized);
        }

        return $labels;
    }

    public static function translateLiteral(string $text, ?string $lang = null): string
    {
        $resolvedLang = self::normalize((string) ($lang ?? self::getActiveLang()));
        if ($resolvedLang === '') {
            $resolvedLang = self::getDefaultLang();
        }

        return self::translateLiteralInternal($text, $resolvedLang, 0);
    }

    private static function loadLanguage(string $lang): void
    {
        $normalized = self::normalize($lang);
        if ($normalized === '' || isset(self::$cache[$normalized])) {
            return;
        }

        $file = __DIR__ . "/../lang/{$normalized}.php";
        self::$cache[$normalized] = is_file($file) ? (require $file) : [];
    }

    private static function translateLiteralInternal(string $text, string $lang, int $depth): string
    {
        if ($text === '' || $depth > 4) {
            return $text;
        }

        $resolvedLang = self::normalize($lang);
        if ($resolvedLang === '') {
            $resolvedLang = self::getDefaultLang();
        }

        $defaultLang = self::getDefaultLang();
        self::loadLanguage($resolvedLang);
        self::loadLanguage($defaultLang);
        self::buildLiteralCaches($resolvedLang);

        if (isset(self::$reverseLiteralCache[$resolvedLang][$text])) {
            return self::$reverseLiteralCache[$resolvedLang][$text];
        }

        $normalizedText = trim((string) (preg_replace('/\s+/u', ' ', $text) ?? $text));
        if ($normalizedText !== '' && $normalizedText !== $text && isset(self::$reverseLiteralCache[$resolvedLang][$normalizedText])) {
            return self::$reverseLiteralCache[$resolvedLang][$normalizedText];
        }

        foreach (self::$patternCache[$resolvedLang] as $pattern) {
            if (!preg_match($pattern['regex'], $text, $matches)) {
                if ($normalizedText === '' || $normalizedText === $text || !preg_match($pattern['regex'], $normalizedText, $matches)) {
                    continue;
                }
            }

            $translated = $pattern['target'];
            foreach ($pattern['placeholders'] as $placeholder) {
                $captured = trim((string) ($matches[$placeholder] ?? ''));
                $replacement = $captured === ''
                    ? $captured
                    : self::translateLiteralInternal($captured, $resolvedLang, $depth + 1);
                $translated = str_replace(':' . $placeholder, $replacement, $translated);
            }

            return $translated;
        }

        return $text;
    }

    private static function buildLiteralCaches(string $lang): void
    {
        $normalized = self::normalize($lang);
        if ($normalized === '' || isset(self::$reverseLiteralCache[$normalized], self::$patternCache[$normalized])) {
            return;
        }

        $defaultLang = self::getDefaultLang();
        $defaultPack = self::$cache[$defaultLang] ?? [];
        $targetPack = self::$cache[$normalized] ?? [];
        $reverse = [];
        $reverseScores = [];
        $patterns = [];

        foreach ($defaultPack as $key => $sourceValue) {
            if (!is_string($sourceValue)) {
                continue;
            }

            $source = trim($sourceValue);
            if ($source === '') {
                continue;
            }

            $targetValue = $targetPack[$key] ?? $sourceValue;
            if (!is_string($targetValue) || trim($targetValue) === '') {
                $targetValue = $sourceValue;
            }

            if (preg_match('/:[A-Za-z][A-Za-z0-9_]*/', $source) === 1) {
                $pattern = self::buildLiteralPattern($source, $targetValue);
                if ($pattern !== null) {
                    $patterns[] = $pattern;
                    continue;
                }
            }

            $score = self::literalPreferenceScore((string) $key, $source, $targetValue);
            if (!isset($reverse[$source]) || $score > ($reverseScores[$source] ?? PHP_INT_MIN)) {
                $reverse[$source] = $targetValue;
                $reverseScores[$source] = $score;
            }
        }

        usort(
            $patterns,
            static fn(array $left, array $right): int => $right['length'] <=> $left['length']
        );

        self::$reverseLiteralCache[$normalized] = $reverse;
        self::$patternCache[$normalized] = $patterns;
    }

    private static function literalPreferenceScore(string $key, string $source, string $target): int
    {
        $score = 0;
        $normalizedKey = self::normalize($key);
        $normalizedSource = self::normalize($source);

        if ($normalizedKey === $normalizedSource) {
            $score += 100;
        }

        if (str_starts_with($normalizedKey, 'literal_')) {
            $score += 40;
        }

        if (str_starts_with($normalizedKey, 'workflow_status_') || str_contains($normalizedKey, '_status_')) {
            $score -= 20;
        }

        if ($source === strtoupper($source) && $target === strtoupper($target)) {
            $score += 10;
        }

        return $score;
    }

    private static function buildLiteralPattern(string $source, string $target): ?array
    {
        $segments = preg_split('/(:[A-Za-z][A-Za-z0-9_]*)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($segments) || $segments === []) {
            return null;
        }

        $regex = '/^';
        $placeholders = [];
        $staticLength = 0;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^:([A-Za-z][A-Za-z0-9_]*)$/', $segment, $matches) === 1) {
                $name = $matches[1];
                $placeholders[] = $name;
                $regex .= '(?P<' . $name . '>.+?)';
                continue;
            }

            $staticLength += strlen($segment);
            $regex .= preg_quote($segment, '/');
        }

        if ($placeholders === [] || $staticLength === 0) {
            return null;
        }

        $regex .= '$/u';

        return [
            'regex' => $regex,
            'target' => $target,
            'placeholders' => array_values(array_unique($placeholders)),
            'length' => strlen($source),
        ];
    }

    private static function normalize(string $lang): string
    {
        return strtolower(trim($lang));
    }
}

// ---------------------------------------------------------
// Global helper function (__t) for convenience in views
// ---------------------------------------------------------
if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        return \App\Shared\Lang::t($key, $replacements);
    }
}
