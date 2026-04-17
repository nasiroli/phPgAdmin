<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Setup gate (redirect to /setup until finished)
    |--------------------------------------------------------------------------
    |
    | Disabled automatically when SETUP_GATE=false (e.g. in phpunit.xml).
    |
    */

    'gate_enabled' => (bool) env('SETUP_GATE', true),

    /*
    |--------------------------------------------------------------------------
    | Lock file written when first-run setup completes
    |--------------------------------------------------------------------------
    */

    'lock_path' => storage_path('app/.setup-complete'),

];
