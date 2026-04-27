<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * TODO: This middleware is not registered in bootstrap/app.php or applied to any route group.
 *       Register it when ready to enable visit logging.
 */
class RecordVisitsLog
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        RateLimiter::attempt(
            'visit-from-'.$request->visitor()->ip(),
            $preMinute = 1,
            fn () => $this->recordVisit($request)
        );

        return $next($request);
    }

    /**
     * @param Closure(Request)
     * @return void
     *
     * @throws RuntimeException
     */
    public function recordVisit(Request $request)
    {
        if (DB::table('visits_log')->where('ip', $request->visitor()->ip())->exists()) {
            return;
        }
        DB::table('visits_log')->insert([
            'ip' => $request->visitor()->ip(),
            'platform' => $request->visitor()->platform(),
            'browser' => $request->visitor()->browser(),
            'languages' => Json::encode($request->visitor()->languages()),
            'device' => $request->visitor()->device(),
            'request' => Json::encode($request->visitor()->request()),
            'useragent' => Json::encode($request->visitor()->useragent()),
        ]);
    }
}
