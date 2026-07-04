<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime Profile
    |--------------------------------------------------------------------------
    |
    | The profile this codebase boots under. "cloud" is the multi-tenant web
    | deployment; "local" is the single-tenant offline desktop client. The
    | profile is read exclusively through App\Support\Runtime — grep for
    | `Runtime::` to find every branch. When RUNTIME_PROFILE is unset the
    | application behaves bit-identically to the cloud web app.
    |
    */

    'profile' => env('RUNTIME_PROFILE', 'cloud'),

];
