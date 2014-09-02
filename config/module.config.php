<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'ZF\Statsd\StatsdListener' => 'ZF\Statsd\StatsdListenerFactory',
        )
    ),

    'zf-statsd' => array(
        /*
         * Whether to enable stats.
         */
        'enable' => true,

        // web-front-01.account.get.200.application-json.application-json-hal.route.memory
        'memory_pattern' => '%hostname%.%controller%.%http-method%.%http-code%.%request-content-type%.%response-content-type%.%mvc-event%.memory',

        // web-front-01.account.get.200.application-json.application-json-hal.route.duration
        'timer_pattern'  => '%hostname%.%controller%.%http-method%.%http-code%.%request-content-type%.%response-content-type%.%mvc-event%.duration',

        // Metrics overriding
        'metric_tokens_callback'     => 'strtolower', // strtoupper, strtolower, ucwords, etc.
        'replace_dots_in_tokens'     => true,
        'replace_special_chars_with' => '-',

        /*
         * StatsD daemon configuration.
         */
        'statsd' => array(
            'host' => '127.0.0.1',
            'port' => '8125',
        ),
    ),
);
