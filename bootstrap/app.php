<?php

use App\Exceptions\InsufficientStockException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleLocale;
use App\Models\Tenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        then: function () {
            Route::middleware('web')
                ->domain(config('app.domain'))
                ->group(base_path('routes/web.php'));

            $tenantRoutes = Route::middleware('web')
                ->domain('{tenant}.'.config('app.domain'));

            if ($reserved = Tenant::reservedSlugs()) {
                $excluded = implode('|', $reserved);
                $tenantRoutes->where(['tenant' => "^(?!(?:{$excluded})$).+$"]);
            }

            $tenantRoutes->group(base_path('routes/tenant.php'));
        },
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function ($request) {
            if (in_array('auth:admin', $request->route()?->middleware() ?? [])) {
                return '/__admin/login';
            }

            return '/login';
        });
        $middleware->redirectUsersTo('/dashboard');

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $middleware->web(append: [
            HandleLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (InsufficientStockException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (
                $request->expectsJson()
                || $request->is('api/*')
                || $response->isRedirection()
                || ! in_array($status, [403, 404, 419, 500, 503])
            ) {
                return $response;
            }

            $homeUrl = match (true) {
                Auth::guard('admin')->check() => '/__admin',
                Auth::check() && app()->bound('currentTenant') => '/dashboard',
                default => '/',
            };

            return Inertia::render('Error', [
                'status' => $status,
                'homeUrl' => $homeUrl,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
