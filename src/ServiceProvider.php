<?php namespace ProVision\Translation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Translation\FileLoader;
use ProVision\Translation\Console\Commands\DumpCommand;
use ProVision\Translation\Console\Commands\FetchCommand;
use ProVision\Translation\Middleware\Injector;

class ServiceProvider extends \Illuminate\Translation\TranslationServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    protected $commands = [
        DumpCommand::class,
        FetchCommand::class
    ];

    /**
     * Alternative pluck to stay backwards compatible with Laravel 5.1 LTS.
     *
     * @param Builder $query
     * @param         $column
     * @param null    $key
     *
     * @return array|mixed
     * @deprecated да го разкарам това
     */
    public static function pluckOrLists(Builder $query, $column, $key = null) {
        if (\Illuminate\Foundation\Application::VERSION < '5.2') {
            $result = $query->lists($column, $key);
        } else {
            $result = $query->pluck($column, $key);
        }

        return $result;
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->mergeConfigFrom(__DIR__ . '/../config/translation.php', 'provision.translation');

        $this->registerDatabase();
        $this->registerLoader();

        $this->commands($this->commands);

        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];
            $database = $app['translation.database'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];

            $trans = new Translator($database, $loader, $locale, $app);

            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });

        $this->app['router']->pushMiddlewareToGroup('web', Injector::class);

    }

    protected function registerDatabase() {
        $this->app->singleton('translation.database', function ($app) {
            return new DatabaseLoader($app);
        });
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader() {
        $this->app->singleton('translation.loader', function ($app) {
            return new FileLoader($app['files'], $app['path.lang']);
        });
    }

    public function boot() {

//        $this->publishes([
//            __DIR__ . '/../config/translation.php' => config_path('provision/translation.php'),
//        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../views', 'translation');

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'translation');
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/provision/translation'),
        ], 'lang');

        $this->publishes([
            __DIR__ . '/../config/translation.php' => config_path('provision/translation.php'),
        ], 'config');

        $this->app['translation.database']->addNamespace(null, null);

        \ProVision\Administration\Administration::bootModule('translation', Administration::class);

        Blade::directive('trans_strip', function ($expression) {
            return trans_strip(str_ireplace("'", '', $expression));
        });
        Blade::directive('strip_trans', function ($expression) {
            return trans_strip(str_ireplace("'", '', $expression));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return array(
            'translator',
            'translation.loader',
            'translation.database'
        );
    }
}
