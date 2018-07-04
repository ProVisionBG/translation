<?php namespace ProVision\Translation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use ProVision\Administration\Facades\Administration;
use ProVision\Translation\ServiceProvider;
use ProVision\Translation\TranslationException;
use ProVision\Translation\Translator;
use Stichoza\GoogleTranslate\TranslateClient;

class TranslationsController extends Controller {

    public function getIndex() {
        return view('translation::index');
    }

    public function getGroups() {
        $query = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->select('group', 'vendor', 'package', 'module')
            ->distinct()
            ->orderBy('group');

        return $query->get();
    }

    public function getLocales() {
//        $query = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
//            ->select('locale')
//            ->distinct()
//            ->orderBy('locale');
//
//        return ServiceProvider::pluckOrLists($query, 'locale');
        return array_keys(Administration::getLanguages());
    }

    public function postItems(Request $request) {
        if (strlen($request->get('translate')) == 0) {
            throw new TranslationException();
        }

        $q = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->select('name', 'value')
            ->where('locale', $request->get('locale'))
            ->where('group', $request->get('group'))
            ->orderBy('name');

        if ($request->has('package')) {
            $q->where('package', $request->package);
        } else {
            $q->whereNull('package');
        }

        if ($request->has('vendor')) {
            $q->where('vendor', $request->vendor);
        } else {
            $q->whereNull('vendor');
        }

        if ($request->has('module')) {
            $q->where('module', $request->module);
        } else {
            $q->whereNull('module');
        }

        //dd($request->all());
        $base = $q->get();

        $new = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->select('name', 'value')
            ->where('locale', strtolower($request->get('translate')))
            ->where('group', $request->get('group'))
            ->orderBy('name');

        if ($request->has('package')) {
            $new->where('package', $request->package);
        } else {
            $new->whereNull('package');
        }

        if ($request->has('vendor')) {
            $new->where('vendor', $request->vendor);
        } else {
            $new->whereNull('vendor');
        }

        if ($request->has('module')) {
            $new->where('module', $request->module);
        } else {
            $new->whereNull('module');
        }

        $new = ServiceProvider::pluckOrLists($new, 'value', 'name');

        foreach ($base as &$item) {
            $translate = null;

            if ($new->has($item->name)) {
                $translate = $new[$item->name];
            }
            $item->translation = $translate;
        }

        return $base;
    }

    public function postStore(Request $request) {
        $q = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('locale', strtolower($request->get('locale')))
            ->where('group', $request->get('group'))
            ->where('name', $request->get('name'));

        if ($request->has('package')) {
            $q->where('package', $request->package);
        } else {
            $q->whereNull('package');
        }

        if ($request->has('vendor')) {
            $q->where('vendor', $request->vendor);
        } else {
            $q->whereNull('vendor');
        }

        if ($request->has('module')) {
            $q->where('module', $request->module);
        } else {
            $q->whereNull('module');
        }

        $item = $q->first();

        $data = [
            'locale' => strtolower($request->get('locale')),
            'group' => $request->get('group'),
            'name' => $request->get('name'),
            'value' => $request->get('value'),
            'updated_at' => date_create(),
        ];

        if ($request->has('package')) {
            $data['package'] = $request->package;
        }

        if ($request->has('vendor')) {
            $data['vendor'] = $request->vendor;
        }

        if ($request->has('module')) {
            $data['module'] = $request->module;
        }

        if ($item === null) {
            $data = array_merge($data, [
                'created_at' => date_create(),
            ]);
            $result = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->insert($data);
        } else {
            $result = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->where('id', $item->id)->update($data);
        }

        if (!$result) {
            throw new TranslationException('Database error...');
        }

        Translator::getCache()->flush();

        return 'OK';
    }

    /**
     * Save translation from live translate
     *
     * @param Request $request
     *
     * @return string
     * @todo: Да се направи валидация с Request
     */
    public function postStoreQuick(Request $request) {

        $inputData = $request->all();

        $q = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('locale', strtolower($inputData['data']['locale']))
            ->where('group', $inputData['data']['group'])
            ->where('name', $inputData['data']['item']);

        if ($request->has('data.package')) {
            $q->where('package', $request->data['package']);
        } else {
            $q->whereNull('package');
        }

        if ($request->has('data.vendor')) {
            $q->where('vendor', $request->data['vendor']);
        } else {
            $q->whereNull('vendor');
        }

        if ($request->has('data.namespace')) {
            $q->where('module', ucfirst($request->data['namespace']));
        } else {
            $q->whereNull('module');
        }

        $item = $q->first();

        $data = [
            'locale' => strtolower($inputData['data']['locale']),
            'group' => $inputData['data']['group'],
            'name' => $inputData['data']['item'],
            'value' => $inputData['value'],
            'updated_at' => date_create(),
        ];

        if ($request->has('data.package')) {
            $data['package'] = $request->data['package'];
        }

        if ($request->has('data.vendor')) {
            $data['vendor'] = $request->data['vendor'];
        }

        if ($request->has('data.namespace')) {
            $data['module'] = ucfirst($request->data['namespace']);
        }

        if ($item === null) {
            $data = array_merge($data, [
                'created_at' => date_create(),
            ]);
            $result = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->insert($data);
        } else {
            $result = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')->where('id', $item->id)->update($data);
        }

        if (!$result) {
            throw new TranslationException('Database error...');
        }

        Translator::getCache()->flush();

        return 'OK';
    }

    public function postTranslate(Request $request) {
//        $text = TranslateClient::translate($request->input('origin'), $request->input('target'), $request->input('text'));
//        $key  = $request->input('key');
//        return compact('key', 'text');
        $text = preg_replace('/(:)(\w+)/', '--$2', $request->input('text'));
        $text = TranslateClient::translate($request->input('origin'), $request->input('target'), $text);
        $text = preg_replace('/(--)(\w+)/', ':$2', $text);
        $key = $request->input('key');
        return compact('key', 'text');
    }

    public function postDelete(Request $request) {
        \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('name', strtolower($request->get('name')))->delete();
        return 'OK';
    }

    public function fetchCommand() {
        Artisan::call('translation:fetch');

        return 'OK';
    }

    public function dumpCommand() {
        Artisan::call('translation:dump');

        return 'OK';
    }
}
