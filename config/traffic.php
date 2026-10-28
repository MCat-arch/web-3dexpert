<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Traffic retention
    |--------------------------------------------------------------------------
    |
    | Raw page-view rows are cheap but unbounded. `traffic:purge` deletes
    | anything older than this many days, so the table holds roughly a
    | quarter's worth of history for the dashboard chart.
    |
    */

    'retention_days' => (int) env('TRAFFIC_RETENTION_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Dashboard cache
    |--------------------------------------------------------------------------
    |
    | Aggregate queries behind the superadmin widgets are cached in Redis for
    | this many seconds. A dashboard refresh becomes a cache read instead of
    | several aggregate scans over site_traffic.
    |
    */

    'cache_seconds' => (int) env('TRAFFIC_CACHE_SECONDS', 60),

];
