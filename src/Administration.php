<?php

/*
 * ProVision Administration, http://ProVision.bg
 * Author: Venelin Iliev, http://veneliniliev.com
 */

namespace ProVision\Translation;

use Illuminate\Support\Facades\Route;
use ProVision\Administration\Contracts\Module;

class Administration implements Module
{
    public function routes($module)
    {
        Route::group([
            'namespace' => 'ProVision\Translation\Controllers',
            'prefix' => 'translations',
        ], function () {
            Route::get('/', [
                'uses' => 'TranslationsController@getIndex',
                'as' => 'translations.index',
            ]);

            Route::get('/groups', [
                'uses' => 'TranslationsController@getGroups',
                'as' => 'translations.groups',
            ]);

            Route::get('/locales', [
                'uses' => 'TranslationsController@getLocales',
                'as' => 'translations.locales',
            ]);

            Route::post('/items', [
                'uses' => 'TranslationsController@postItems',
                'as' => 'translations.items',
            ]);

            Route::post('/store', [
                'uses' => 'TranslationsController@postStore',
                'as' => 'translations.store',
            ]);

            Route::post('/translate', [
                'uses' => 'TranslationsController@postTranslate',
                'as' => 'translations.translate',
            ]);

            Route::post('/delete', [
                'uses' => 'TranslationsController@postDelete',
                'as' => 'translations.delete',
            ]);

            Route::post('/fetch-command', [
                'uses' => 'TranslationsController@fetchCommand',
                'as' => 'translations.fetch-command',
            ]);

            Route::post('/dump-command', [
                'uses' => 'TranslationsController@dumpCommand',
                'as' => 'translations.dump-command',
            ]);
        });
    }

    public function dashboard($module)
    {
        //
    }

    public function menu($module)
    {
        \AdministrationMenu::addSystem(trans('administration::index.translates'), [
            'icon' => 'globe',
            'route' => \ProVision\Administration\Facades\Administration::routeName('translations.index'),
            'target' => '_blank'
        ]);

    }
}
