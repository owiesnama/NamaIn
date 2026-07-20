<?php

use Illuminate\Support\Facades\Route;

/**
 * Route::resource() registers create/edit routes whether or not the controller
 * implements them. A route pointing at a missing method throws
 * BadMethodCallException at dispatch — a 500 that no amount of local clicking
 * finds, because nothing in the UI links to the dead route.
 */
it('registers no route pointing at a missing controller method', function () {
    $broken = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getActionName())
        ->filter(fn ($action) => str_contains($action, '@'))
        ->filter(fn ($action) => str_starts_with($action, 'App\\'))
        ->unique()
        ->reject(function ($action) {
            [$class, $method] = explode('@', $action, 2);

            return ! class_exists($class) || method_exists($class, $method);
        })
        ->values();

    expect($broken->all())->toBe([]);
});
