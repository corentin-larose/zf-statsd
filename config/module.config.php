<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'ZF\Statsd\StatsdListener' => 'ZF\Statsd\StatsdListenerFactory',
        )
    ),

    'zf-statsd' => array(
        'counter'          => true,
        'ram_gauge'        => true,
        'sample_rate'      => 1,
        'timer'            => true,

        /*
         * Whether to enable http cache.
         */
        'enable' => true,

        // Prefixes
        'counter_prefix' => '',
        'gauge_prefix'   => '',
        'metric_prefix'  => gethostname(),
        'timer_prefix'   => '',

        // Suffixes
        'counter_suffix' => 'count',
        'gauge_suffix'   => '',
        'timer_suffix'   => '',

        // Chars and case overriding
        'override_case_callback'     => 'strtolower', // strtoupper, strtolower, ucwords, etc.
        'replace_dots'               => true,
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
