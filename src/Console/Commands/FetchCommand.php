<?php namespace ProVision\Translation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ProVision\Administration\Facades\Administration;
use Symfony\Component\Console\Input\InputOption;

class FetchCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translation:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches translations from language files and imports it into the database.';

    protected $lang_path = null;
    protected $locales = null;

    public function __construct()
    {
        $this->lang_path = base_path() . '/resources/lang';
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        if (!$this->validate()) {
            return false;
        }

        $locales = $this->usableLocales();
        foreach ($locales as $locale) {
            $groups = $this->usableGroups($locale);
            foreach ($groups as $group) {
                $this->storeGroup($locale, $group);
            }
        }

        /*
         * vendors
         */
        $vendors = File::directories($this->lang_path . '/vendor');
        foreach ($vendors as $vendor) {
            $packages = File::directories($vendor);
            foreach ($packages as $package) {
                $this->storeVendorPackage($vendor, $package);
            }
        }
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        $locale = $this->option('locale');
        if ($locale !== null) {
            if (!$this->hasLocale($locale)) {
                $this->error("The locale '{$locale}' was not found.");
                return false;
            }
        }

        $group = $this->option('group');
        if ($group !== null) {
            if ($locale === null) {
                foreach ($this->getLocales() as $locale) {
                    $this->validateGroup($locale, $group);
                }
            } else {
                $this->validateGroup($locale, $group);
            }

        }
        return true;
    }

    /**
     * @param $locale
     * @return bool
     */
    protected function hasLocale($locale)
    {
        $result = false;
        if (array_search($locale, $this->getLocales()) !== false) {
            $result = true;
        }
        return $result;
    }

    /**
     * @return array|null
     */
    protected function getLocales()
    {

        return array_keys(Administration::getLanguages());

//        if ($this->locales === null) {
//            $locales = \File::directories($this->lang_path);
//            $this->locales = array_map([$this, 'cleanLocaleDir'], $locales);
//        }
//        $locales = $this->locales;
//        return $locales;
    }

    /**
     * @param $locale
     * @param $group
     */
    protected function validateGroup($locale, $group)
    {
        if (!$this->hasGroup($locale, $group)) {
            $this->error("The file '{$group}.php' was not found within locale '{$locale}'.");
        }
    }

    protected function hasGroup($locale, $group)
    {
        $result = false;
        $file = $this->lang_path . "/{$locale}/{$group}.php";
        if (\File::exists($file)) {
            $result = true;
        }
        return $result;
    }

    /**
     * @return array|null
     */
    protected function usableLocales()
    {
        $locales = [];
        if ($this->option('locale') !== null) {
            $locales[] = $this->option('locale');
            return $locales;
        } else {
            $locales = $this->getLocales();
            return $locales;
        }
    }

    /**
     * @param $locale
     * @return array|null
     */
    protected function usableGroups($locale)
    {
        $groups = [];
        if ($this->option('group') !== null) {
            $groups[] = $this->option('group');
            return $groups;
        } else {
            $groups = $this->getGroups($locale);
            return $groups;
        }
    }

    /**
     * @return array|null
     */
    protected function getGroups($locale)
    {
        $path = $this->lang_path . "/{$locale}";
        $groups = \File::files($path);
        foreach ($groups as &$group) {
            $group = $this->cleanGroupDir($group, $locale);
        }
        return $groups;
    }

    protected function cleanGroupDir($item, $locale, $vendor = false, $package = false)
    {
        $toCleanPath = $this->lang_path . "/{$locale}/";

        if ($vendor) {
            $toCleanPath .= $vendor . '/';
        }

        if ($package) {
            $toCleanPath .= $package . '/';
        }

        $clean = str_replace($toCleanPath, '', $item);
        if (preg_match('/^(.*?)\.php$/sm', $clean, $match)) {
            return $match[1];
        }
        return false;
    }

    /**
     * @param $locale
     * @param $group
     */
    protected function storeGroup($locale, $group)
    {
        $keys = require $this->lang_path . "/{$locale}/{$group}.php";
        $keys = $this->flattenArray($keys);

        $updated = 0;
        $inserted = 0;
        foreach ($keys as $name => $value) {
            list($inserted, $updated) = $this->storeTranslation($locale, $group, $name, $value, $inserted, $updated);
        }

        $this->flushCache($locale, $group);

        $this->info("Fetched {$locale}/{$group}.php [New: {$inserted}, Updated: {$updated}]");
    }

    protected function flattenArray($keys, $prefix = '')
    {
        $result = [];
        foreach ($keys as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $prefix . $key . '.'));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param $locale
     * @param $group
     * @param $name
     * @param $value
     * @param $inserted
     * @param $updated
     * @param bool $vendor
     * @param bool $package
     * @return array
     */
    protected function storeTranslation($locale, $group, $name, $value, $inserted, $updated, $vendor = false, $package = false)
    {
        $q = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('locale', $locale)
            ->where('group', $group)
            ->where('name', $name);

        if ($vendor) {
            $q->where('vendor', $vendor);
        }

        if ($package) {
            $q->where('package', $package);
        }

        $item = $q->first();

        $data = compact('locale', 'group', 'name', 'value');

        if ($vendor) {
            $data['vendor'] = $vendor;
        }

        if ($package) {
            $data['package'] = $package;
        }

        $data = array_merge($data, [
            'updated_at' => date_create(),
        ]);

        if ($item === null) {
            $data = array_merge($data, [
                'created_at' => date_create(),
            ]);
            \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->insert($data);
            $inserted++;
            return array($inserted, $updated);
        } else {
            \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->where('id', $item->id)->update($data);
            $updated++;
            return array($inserted, $updated);
        }
    }

    /**
     * @param $locale
     * @param $group
     */
    protected
    function flushCache($locale, $group)
    {
        \Cache::forget('__translations.' . $locale . '.' . $group);
    }

    protected
    function storeVendorPackage($vendor, $package)
    {
        $vendor = basename($vendor);
        $package = basename($package);

        $packageDir = $this->lang_path . "/vendor/{$vendor}/{$package}/";

        foreach ($this->getLocales() as $locale) {
            $packageLocaleDir = $packageDir . '/' . $locale;

            if (!File::exists($packageLocaleDir)) {
                continue;
            }

            $groups = \File::files($packageLocaleDir);
            foreach ($groups as &$group) {
                $group = $this->cleanGroupDir($group, $locale, $vendor, $package);
                $group = basename($group);
                $keys = require $this->lang_path . "/vendor/{$vendor}/{$package}/{$locale}/{$group}.php";
                $keys = $this->flattenArray($keys);

                $updated = 0;
                $inserted = 0;
                foreach ($keys as $name => $value) {
                    list($inserted, $updated) = $this->storeTranslation($locale, $group, $name, $value, $inserted, $updated, $vendor, $package);
                }

                $this->flushCache($locale, $group);

                $this->info("Fetched {$vendor}/{$package}/{$locale}/{$group}.php [New: {$inserted}, Updated: {$updated}]");
            }


        }


    }

    protected
    function cleanLocaleDir($item)
    {
        return basename($item);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected
    function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected
    function getOptions()
    {
        return [
            ['locale', 'l', InputOption::VALUE_OPTIONAL, 'Specify a locale.', null],
            ['group', 'g', InputOption::VALUE_OPTIONAL, 'Specify a group.', null],
        ];
    }
}