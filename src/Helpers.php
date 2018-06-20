<?php
if (!function_exists('trans_strip')) {
    /**
     * Translate the given message and return strip tags.
     *
     * @param  string $key
     * @param  array  $replace
     * @param  string $locale
     *
     * @return \Illuminate\Contracts\Translation\Translator|string|array|null
     */
    function trans_strip($key, $replace = [], $locale = null): string {
        return strip_tags(trans($key, $replace, $locale));
    }
}

if (!function_exists('strip_trans')) {
    /**
     * Translate the given message and return strip tags.
     *
     * @param  string $key
     * @param  array  $replace
     * @param  string $locale
     *
     * @return \Illuminate\Contracts\Translation\Translator|string|array|null
     */
    function strip_trans($key, $replace = [], $locale = null): string {
        return trans_strip($key, $replace, $locale);
    }
}