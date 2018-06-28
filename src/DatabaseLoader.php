<?php namespace ProVision\Translation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\DB;

class DatabaseLoader implements Loader {

    protected $_app = null;

    /**
     * All of the namespace hints.
     *
     * @var array
     */
    protected $hints = [];

    public function __construct(Application $app) {
        $this->_app = $app;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string $locale
     * @param  string $group
     * @param  string $namespace
     *
     * @return array
     */
    public function load($locale, $group, $namespace = null) {
        $query = DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('locale', $locale)
            ->where('group', $group);

        return ServiceProvider::pluckOrLists($query, 'value', 'name')->toArray();
    }

    /**
     * Add a new namespace to the loader.
     * This function will not be used but is required
     * due to the LoaderInterface.
     * We'll just leave it here as is.
     *
     * @param  string $namespace
     * @param  string $hint
     *
     * @return void
     */
    public function addNamespace($namespace, $hint) {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Adds a new translation to the database or
     * updates an existing record if the viewed_at
     * updates are allowed.
     *
     * @param string $locale
     * @param string $group
     * @param        $key
     * @param        $namespace
     *
     * @return void
     * @internal param string $name
     */
    public function addTranslation($locale, $group, $key, $namespace) {
        if (!\Config::get('app.debug') || \Config::get('provision.translation.minimal')) {
            return;
        }

        $package = false;

        if (strpos($key, '::')) {
            //use package
            if (preg_match('/^(?P<package>([a-z0-9_\-]*))::(?P<group>[a-z0-9_\-]*)\.(?P<name>[a-z0-9_\-]*)/x', $key, $regs)) {
                $package = $regs['package'];
                $group = $regs['group'];
                $name = $regs['name'];
            } else {
                throw new TranslationException('Could not extract key from translation package (namespace).');
            }

        } elseif (preg_match('/^(?P<group>[a-z0-9_\-]*)\.(?P<name>[a-z0-9_\-]*)$/', $key, $regs)) {
            $group = $regs['group'];
            $name = $regs['name'];
        } else {
            return;
            //throw new TranslationException('Could not extract key from translation.');
        }

        $q = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('locale', $locale)
            ->where('group', $group)
            ->where('name', $name);

        if ($package) {
            $q->where('package', $package);
        }

        $item = $q->first();

        $data = compact('locale', 'group', 'name');
        $data = array_merge($data, [
            'viewed_at' => date_create(),
            'updated_at' => date_create(),
        ]);

        if ($package) {
            $data['package'] = $package;
        }

        if ($item === null) {
            $data = array_merge($data, [
                'created_at' => date_create(),
            ]);
            \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->insert($data);
        } else {
            if ($this->_app['config']->get('provision.translation.update_viewed_at')) {
                \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->where('id', $item->id)->update($data);
            }
        }
    }

    /**
     * Get an array of all the registered namespaces.
     * This function will not be used but is required
     * due to the LoaderInterface.
     * We'll just leave it here as is.
     *
     * @return void
     */
    public function namespaces() {
        return $this->hints;
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string $path
     *
     * @return void
     */
    public function addJsonPath($path) {

    }
}
