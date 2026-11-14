<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ConfigAggregator;

return [
    // Toggle the configuration cache. Development mode turns it off. Clear it with `composer clear-config-cache`.
    ConfigAggregator::ENABLE_CACHE => true,
    'debug'                        => false,
    'mezzio'                       => [
        'error_handler' => [
            'template_404'   => 'error::404',
            'template_error' => 'error::error',
            'layout'         => 'layout::auth',
        ],
    ],
];
