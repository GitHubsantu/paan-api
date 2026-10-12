<?php

return [
    'max_apk_mb' => (int) env('APK_MAX_MB', 150),
    // Owner must confirm every claim before adding it here.
    'promises' => [],
    'use_x_accel' => false,
    'cache_ttl' => 60,
];
