<?php namespace ProVision\Translation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\LoaderInterface;
use ProVision\Administration\Facades\Settings;

class Translator extends \Illuminate\Translation\Translator {

    protected $app = null;

    public function __construct(DatabaseLoader $database, FileLoader $loader, $locale, Application $app) {
        $this->database = $database;
        $this->app = $app;
        parent::__construct($loader, $locale);
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string $key
     * @param  array  $replace
     * @param  string $locale
     * @param  bool   $fallback
     *
     * @return string
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true) {
        list($namespace, $group, $item) = $this->parseKey($key);

        // Here we will get the locale that should be used for the language line. If one
        // was not passed, we will use the default locales which was given to us when
        // the translator was instantiated. Then, we can load the lines and return.
        $locales = $fallback ? $this->localeArray($locale)
            : [$locale ?: $this->locale];

        foreach ($locales as $locale) {
            if (!is_null($line = $this->getLine(
                $namespace, $group, $locale, $item, $replace
            ))
            ) {
                break;
            }
        }

        // If the line doesn't exist, we will return back the key which was requested as
        // that will be quick to spot in the UI if language keys are wrong or missing
        // from the application's language files. Otherwise we can return the line.
        if (isset($line)) {
            if (is_string($line) && $this->isLiveTranslate()) {
                return '<span style="border:1px dashed gray;" class="translate-item" data-namespace="' . $namespace . '" data-item="' . $item . '" data-group="' . $group . '" data-key="' . $key . '" data-locale="' . $locale . '" contenteditable="true">' . $line . '</span>';
            } else {
                return $line;
            }
        }

        if (Config::get('provision.translation.log_missing_keys')) {
            Log::debug('Missing translation key: ' . $key);
        }

        if ($this->isLiveTranslate()) {
            return '<span style="border:1px dashed gray;" class="translate-item" data-namespace="' . $namespace . '" data-item="' . $item . '" data-group="' . $group . '" data-key="' . $key . '" data-locale="' . $locale . '" contenteditable="true">' . $key . '</span>';
        }

        return $key;
    }

    /**
     * Is live translate enable
     *
     * @return bool
     */
    private function isLiveTranslate(): bool {
        return Auth::guard(config('provision_administration.guard'))->check() && !\ProVision\Administration\Facades\Administration::routeInAdministration() && Settings::get('live_translate');
    }

    public function load($namespace, $group, $locale) {
        if ($this->isLoaded($namespace, $group, $locale)) {
            return;
        }

        // If a Namespace is give the Filesystem will be used
        // otherwise we'll use our database.
        // This will allow legacy support.
        if (self::isNamespaced($namespace) && !\ProVision\Administration\Facades\Administration::routeInAdministration()) {
            // If debug is off then cache the result forever to ensure high performance.
            if (!Config::get('app.debug') || Config::get('provision.translation.minimal')) {
                $that = $this;
                $lines = Translator::getCache()->rememberForever(Translator::getCacheKey($namespace, $locale, $group), function () use ($that, $locale, $group, $namespace) {
                    return $that->loadFromDatabase($namespace, $group, $locale);
                });
            } else {
                $lines = $this->loadFromDatabase($namespace, $group, $locale);
            }
        } else {
            $lines = $this->loader->load($locale, $group, $namespace);
        }

        $this->loaded[$namespace][$group][$locale] = $lines;
    }

    protected static function isNamespaced($namespace) {
        return $namespace != '*';
    }

    /**
     * @return Cache
     */
    static function getCache() {
        return Cache::tags('translations');
    }

    /**
     * Ключ за кеширане на данните
     *
     * @param      $namespace
     * @param      $locale
     * @param      $group
     *
     * @param null $package
     * @param null $vendor
     *
     * @return string
     */
    static function getCacheKey($namespace = null, $locale = null, $group = null, $package = null, $vendor = null): string {
        return strtolower('__translations.' . $namespace . '.' . $package . '.' . $vendor . '.' . $locale . '.' . $group);
    }

    /**
     * @param $namespace
     * @param $group
     * @param $locale
     *
     * @return array
     */
    protected function loadFromDatabase($namespace, $group, $locale) {
        $lines = $this->database->load($locale, $group, $namespace);

        if ($lines->count() == 0 && Config::get('provision.translation.file_fallback', true)) {
            $lines = $this->loader->load($locale, $group, $namespace);
            return $lines;
        }

        return $lines;
    }

    /**
     * Get the array of locales to be checked.
     *
     * Compatibility with laravel 5.4
     *
     * @param  string|null $locale
     *
     * @return array
     */
    protected function parseLocale($locale) {
        return array_filter([
            $locale ?: $this->locale,
            $this->fallback
        ]);
    }
}
