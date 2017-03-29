<?php namespace ProVision\Translation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Symfony\Component\Console\Input\InputOption;

class DumpCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translation:dump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Will dump database translations into classic language files.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $query = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->select('locale', 'group', 'name', 'value', 'package', 'vendor', 'module');
        $this->addOptionToQuery($query, 'locale');
        $this->addOptionToQuery($query, 'group');
        $results = $query->get();

        // Reorder the data
        $dump = [];
        $vendorsData = [];
        $modulesData = [];
        foreach ($results as $result) {
            if (!$result->vendor && !$result->package && !$result->module) {
                $dump[$result->locale][$result->group][$result->name] = $result->value;
            } elseif ($result->vendor && $result->package && !$result->module) {
                $vendorsData[$result->vendor][$result->package][$result->locale][$result->group][$result->name] = $result->value;
            } elseif ($result->module) {
                $modulesData[$result->module][$result->locale][$result->group][$result->name] = $result->value;
            } else {
                $this->error(print_r($result, true));
            }
        }

        $this->write($dump);
        $this->writeVendors($vendorsData);
        $this->writeModules($modulesData);
    }

    /**
     * @param Builder $query
     * @param string $option
     */
    protected function addOptionToQuery(Builder $query, $option)
    {
        if ($this->option($option) !== null) {
            $query->where($option, $this->option($option));
        }
    }

    protected function write($dump)
    {
        $lang_path = base_path() . '/resources/lang';
        $date = date_create()->format('Y-m-d H:i:s');
        foreach ($dump as $locale => $groups) {
            foreach ($groups as $group => $content) {
                $path = $lang_path . "/{$locale}";
                if (!\File::exists($path)) {
                    \File::makeDirectory($path, 0755, true);
                }

                $file = $path . "/{$group}.php";
                $content = $this->fixNulledValues($content);
                $data = $this->getFileTemplate($content, $locale, $group, $date);

                // Display the results
                if (\File::put($file, $data)) {
                    $this->info("Dumped: {$file}");
                } else {
                    $this->error("Failed to dump: {$file}");
                }
            }
        }
    }

    /**
     * @param $content
     * @return mixed
     */
    protected function fixNulledValues($content)
    {
        foreach ($content as $key => $value) {
            if ($value === null) {
                $content[$key] = $key;
            }
        }
        return $content;
    }

    /**
     * @param $content
     * @param $locale
     * @param $group
     * @param $date
     * @param bool $vendor
     * @param bool $package
     * @param bool $module
     * @return string
     */
    protected function getFileTemplate($content, $locale, $group, $date, $vendor = false, $package = false, $module = false)
    {
        $content = $this->convertToOrderedNestedArray($content);

        $array_text = var_export($content, true);
        $array_text = $this->getArrayText($content);

        $data = <<<EOF
<?php
// Generated by Translations Manager - ProVision\Translation
// File: {$locale}/{$group}.php
// Vendor: {$vendor}
// Package: {$package}
// Date: {$date}
// Module: {$module}
return {$array_text};
EOF;
        return $data;
    }

    /**
     * @param $content
     * @return array
     */
    private function convertToOrderedNestedArray($content)
    {
        $new_content = array();
        foreach ($content as $key => $value) {
            // Quick fix, see: https://github.com/hpolthof/laravel-translations-db/issues/13
            //$this->assignArrayByPath($new_content, $key, $value);
            if ($value) {
                array_set($new_content, $key, $value);
            }
            ksort($new_content);
        }
        $content = $new_content;
        return $content;
    }

    private function getArrayText($arr, $ident_size = 4, $level = 0)
    {
        $ident = str_repeat(' ', $ident_size * $level);
        $result = "array(\n";

        $max_key_length = $this->getMaxKeyLength($arr);

        foreach ($arr as $key => $value) {

            $filler = str_repeat(' ', $max_key_length - strlen($key));

            if (is_array($value)) {
                $result .= $ident . str_repeat(' ', $ident_size) . "'{$key}' => ";
                $result .= $this->getArrayText($value, $ident_size, $level + 1);
            } else {
                $result .= $ident . str_repeat(' ', $ident_size) . "'{$key}'{$filler} => ";
                $result .= var_export($value, true);
            }
            $result .= ",\n";
        }
        $result .= $ident . ")";

        //if($level > 0) $result .= ",\n";

        return $result;
    }

    /**
     * @param $arr
     * @return int
     */
    private function getMaxKeyLength($arr)
    {
        $keys = array_keys($arr);
        usort($keys, function ($a, $b) {
            return strlen($a) < strlen($b);
        });
        $max_key_length = strlen(array_shift($keys));
        return $max_key_length;
    }

    protected function writeVendors($data)
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $vendor => $packages) {
            foreach ($packages as $package => $dump) {
                $lang_path = base_path() . '/resources/lang/vendor/' . $vendor . '/' . $package;
                $date = date_create()->format('Y-m-d H:i:s');

                foreach ($dump as $locale => $groups) {

                    foreach ($groups as $group => $content) {
                        $path = $lang_path . "/{$locale}";
                        if (!\File::exists($path)) {
                            \File::makeDirectory($path, 0755, true);
                        }

                        $file = $path . "/{$group}.php";
                        $content = $this->fixNulledValues($content);
                        $data = $this->getFileTemplate($content, $locale, $group, $date, $vendor, $package);

                        // Display the results
                        if (\File::put($file, $data)) {
                            $this->info("Dumped: {$file}");
                        } else {
                            $this->error("Failed to dump: {$file}");
                        }
                    }
                }
            }
        }

    }

    protected function writeModules($data)
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $module => $dump) {
            $lang_path = config('modules.path') . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'Lang';
            $date = date_create()->format('Y-m-d H:i:s');

            foreach ($dump as $locale => $groups) {

                foreach ($groups as $group => $content) {
                    $path = $lang_path . DIRECTORY_SEPARATOR . $locale;
                    if (!\File::exists($path)) {
                        \File::makeDirectory($path, 0755, true);
                    }

                    $file = $path . "/{$group}.php";
                    $content = $this->fixNulledValues($content);
                    $data = $this->getFileTemplate($content, $locale, $group, $date, false, false, $module);

                    // Display the results
                    if (\File::put($file, $data)) {
                        $this->info("Dumped: {$file}");
                    } else {
                        $this->error("Failed to dump: {$file}");
                    }
                }
            }
        }


    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['locale', 'l', InputOption::VALUE_OPTIONAL, 'Specify a locale.', null],
            ['group', 'g', InputOption::VALUE_OPTIONAL, 'Specify a group.', null],
        ];
    }

    private function assignArrayByPath(&$arr, $path, $value)
    {
        $keys = explode('.', $path);

        while ($key = array_shift($keys)) {
            $arr = &$arr[$key];
            if (is_array($arr)) {
                ksort($arr);
            }

        }

        $arr = $value;
    }

}
