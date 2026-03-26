<?php

return [
    'default_emd_amount' => (float) env('EMD_DEFAULT_AMOUNT', 10000),
    'penalty_percentage' => (float) env('EMD_PENALTY_PERCENTAGE', 25),
    'payment_window_hours' => (int) env('EMD_PAYMENT_WINDOW_HOURS', 24),
    'max_default_before_block' => (int) env('EMD_MAX_DEFAULT_BEFORE_BLOCK', 3),
    'default_emd_multiplier' => (float) env('EMD_DEFAULT_MULTIPLIER', 1),
];

