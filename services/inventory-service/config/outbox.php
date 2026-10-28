<?php

return [
    // log = storage/logs/outbox-events.log ekata liyanawa, http = Reporting service ekata POST karanawa
    'sink'  => env('OUTBOX_SINK', 'log'),
    'url'   => env('REPORTING_EVENTS_URL'),
    'batch' => 100,
];