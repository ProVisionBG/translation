<?php

/*
 * ProVision Administration, http://ProVision.bg
 * Author: Venelin Iliev, http://veneliniliev.com
 */

namespace ProVision\Translation\Middleware;

use Closure;
use File;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use ProVision\Administration\Facades\Settings;

class Injector {
    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     *
     * @return void
     */
    public function __construct(Application $app) {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        /**
         * Stop for json request
         */
        if ($request->isJson()) {
            return $response;
        }

        /**
         * Ако не е логнат като админ
         * ако не е в админа
         * и ако е пуснат лайв транслейт
         */
        if (!Auth::guard(config('provision_administration.guard'))->check() || \ProVision\Administration\Facades\Administration::routeInAdministration() || !Settings::get('live_translate')) {
            return $response;
        }

        $content = $response->getContent();

        $contentToInject = '
        
        <script>
        
            $(function() {
                $(document).on("blur", "span.translate-item[contenteditable=true]", function() {
                    var r = confirm("Сигурни ли сте, че искате да запазите промените?");
                    if (r == true) {
                         $.get( "' . route('provision.administration.module.translations.store-quick') . '",{
                                value: $(this).text(),
                                data:  $(this).data(),
                                _token: "' . Session::token() . '"                               
                            },
                            function(data) {
                              //alert(data);
                            });
                    } else {
                       return false;
                    }
                   
                });
            });       
        </script>
        ';

        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $contentToInject . substr($content, $pos);
        } else {
            $content = $content . $contentToInject;
        }

        // Update the new content and reset the content length
        $response->setContent($content);
        $response->headers->remove('Content-Length');

        return $response;
    }
}
