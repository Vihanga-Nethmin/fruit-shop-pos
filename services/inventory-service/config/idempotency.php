<?php

return [
    // true nam Idempotency-Key nathuwa POST yawanna bari (400)
    'required' => (bool) env('IDEMPOTENCY_REQUIRED', false),
    // keys retain karana dawas ganana
    'ttl_days' => 7,
];