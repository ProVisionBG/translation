<?php

return [
    /**
     * To save some time interacting with the database, you can turn
     * the storing of the viewed_at field off.
     */
    'update_viewed_at' => true,

    /**
     * - Force translations to be cached, even in Debug Mode.
     * - And disables the collection of new keys.
     * This can be used to prevent lots of queries from
     * happening.
     */
    'minimal' => false,

    /**
     * Use locales from files as a fallback option. Be aware that
     * locales are loaded as groups. When just one locale of a group
     * exists in the database, a file will never be used.
     * To use some files, keep these groups fully out of your database.
     */
    'file_fallback' => true,

    /**
     * Throw exception
     */
    'throw_exception' => false,

    /**
     * Log missing keys in error log
     */
    'log_missing_keys' => true
];
