<?php namespace ProVision\Translation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use ProVision\Administration\Facades\Administration;
use ProVision\Translation\ServiceProvider;
use ProVision\Translation\TranslationException;
use Stichoza\GoogleTranslate\TranslateClient;

class TranslationsController extends Controller
{

    public function getIndex()
    {
        return view('translation::index');
    }

    public function getGroups()
    {
        $query = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->select('group', 'vendor', 'package')
            ->distinct()
            ->orderBy('group');

        return $query->get();
    }

    public function getLocales()
    {
//        $query = \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
//            ->select('locale')
//            ->distinct()
//            ->orderBy('locale');
//
//        return ServiceProvider::pluckOrLists($query, 'locale');
        return array_keys(Administration::getLanguages());
    }

    public function postItems(Request $request)
    {
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

    public function postStore(Request $request)
    {
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
        return 'OK';
    }

    public function postTranslate(Request $request)
    {
//        $text = TranslateClient::translate($request->input('origin'), $request->input('target'), $request->input('text'));
//        $key  = $request->input('key');
//        return compact('key', 'text');
        $text = preg_replace('/(:)(\w+)/', '--$2', $request->input('text'));
        $text = TranslateClient::translate($request->input('origin'), $request->input('target'), $text);
        $text = preg_replace('/(--)(\w+)/', ':$2', $text);
        $key = $request->input('key');
        $key = $request->input('key');
        return compact('key', 'text');
    }

    public function postDelete(Request $request)
    {
        \DB::connection(env('DB_CONNECTION_TRANSLATIONS'))->table('translations')
            ->where('name', strtolower($request->get('name')))->delete();
        return 'OK';
    }

    public function fetchCommand()
    {
        Artisan::call('translation:fetch');

        return 'OK';
    }

    public function dumpCommand()
    {
        Artisan::call('translation:dump');

        return 'OK';
    }
}
