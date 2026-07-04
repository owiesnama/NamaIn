<?php

use App\Providers\AppServiceProvider;
use App\Providers\FeatureServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\JetstreamServiceProvider;
use App\Providers\TelescopeServiceProvider;

/*
 * Runtime seam S2 (Design 03 §2.2): Horizon and Telescope register only under
 * the cloud profile; the offline client overlays bootstrap/local-providers.php
 * with its own providers. env() is read directly because the container does
 * not exist yet at this point of the bootstrap.
 */
$cloud = env('RUNTIME_PROFILE', 'cloud') === 'cloud';

$providers = array_values(array_filter([
    AppServiceProvider::class,
    FeatureServiceProvider::class,
    FortifyServiceProvider::class,
    $cloud ? HorizonServiceProvider::class : null,
    JetstreamServiceProvider::class,
    $cloud ? TelescopeServiceProvider::class : null,
]));

if (file_exists(__DIR__.'/local-providers.php')) {
    $providers = array_merge($providers, require __DIR__.'/local-providers.php');
}

return $providers;
