<?php

namespace App\I18n;

/**
 * Internationalization helper class
 * Handles language switching and translation retrieval
 */
class I18n
{
    private static ?string $currentLocale = 'en';
    private static array $translations = [];
    private static array $supportedLocales = ['en', 'fr'];

    /**
     * Initialize the I18n system
     */
    public static function init(): void
    {
        // Load all translation files
        self::loadTranslations();
        
        // Check for saved locale in session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['locale'])) {
            self::$currentLocale = $_SESSION['locale'];
        } elseif (isset($_COOKIE['petconnect_locale'])) {
            self::$currentLocale = $_COOKIE['petconnect_locale'];
        }
    }

    /**
     * Load all translation files from the Translations directory
     */
    private static function loadTranslations(): void
    {
        $translationsDir = dirname(__DIR__, 2) . '/Translations';
        
        if (!is_dir($translationsDir)) {
            return;
        }

        $files = glob($translationsDir . '/messages.*.php');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/messages\.(.+)\.php/', $filename, $matches)) {
                $locale = $matches[1];
                self::$translations[$locale] = require $file;
            }
        }
    }

    /**
     * Get a translated string
     * 
     * @param string $key The translation key (e.g., 'nav.home')
     * @param array $params Optional parameters for string interpolation
     * @return string The translated string
     */
    public static function trans(string $key, array $params = []): string
    {
        $translations = self::$translations[self::$currentLocale] ?? [];
        
        $message = $translations[$key] ?? self::$translations['en'][$key] ?? $key;
        
        // Handle parameter interpolation
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $message = str_replace(':' . $param, $value, $message);
            }
        }
        
        return $message;
    }

    /**
     * Get a translated string (alias for trans)
     */
    public static function t(string $key, array $params = []): string
    {
        return self::trans($key, $params);
    }

    /**
     * Set the current locale
     * 
     * @param string $locale The locale to set
     * @return bool True if successful, false if locale is not supported
     */
    public static function setLocale(string $locale): bool
    {
        if (!in_array($locale, self::$supportedLocales)) {
            return false;
        }

        self::$currentLocale = $locale;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['locale'] = $locale;
        }

        // Set cookie for 30 days
        setcookie('petconnect_locale', $locale, time() + (30 * 24 * 60 * 60), '/');

        return true;
    }

    /**
     * Get the current locale
     * 
     * @return string The current locale
     */
    public static function getLocale(): string
    {
        return self::$currentLocale;
    }

    /**
     * Get all supported locales
     * 
     * @return array Array of supported locale codes
     */
    public static function getSupportedLocales(): array
    {
        return self::$supportedLocales;
    }

    /**
     * Check if a locale is supported
     * 
     * @param string $locale The locale to check
     * @return bool True if supported, false otherwise
     */
    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::$supportedLocales);
    }

    /**
     * Get all translations for the current locale
     * 
     * @return array Array of translations
     */
    public static function getAll(): array
    {
        return self::$translations[self::$currentLocale] ?? [];
    }

    /**
     * Get translations as a flat array for Twig global access
     * 
     * @return array Flat key-value array for Twig
     */
    public static function forTwig(): array
    {
        $translations = self::$translations[self::$currentLocale] ?? [];
        $flat = [];
        
        foreach ($translations as $key => $value) {
            $flat[$key] = $value;
        }
        
        return $flat;
    }
}

// Initialize I18n on include
I18n::init();