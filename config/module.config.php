<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'ZF\Statsd\StatsdListener' => 'ZF\Statsd\StatsdListenerFactory',
        )
    ),

    'zf-statsd' => array(
        'controllers' => array(
            '<controller>' => array(
                '<http-method>'  => array(
                    '<http-code>'  => array(
                        '<http-content-type>'  => array(
                            'counter'          => true,
                            'name'             => '',
                            'ram_gauge'        => true,
                            'sample_rate'      => 1,
                            'timer'            => true,
                        ),
                    ),
                ),
            ),
        ),

        /*
         * Counter metric name:
         * [<metric_prefix>.][<counter_prefix>.]<controller>.<http-method>.<http-code>.<http-content-type>[.<name>][.<counter_suffix>]
         * For instance: php-fpm-01.counters.my-controller.post.201.application-json-hal.signin-in.count
         *
         * Gauge metric name:
         * [<metric_prefix>.][<gauge_prefix>.]<controller>.<http-method>.<http-code>.<http-content-type>[.<name>][.<gauge_suffix>]
         * For instance: php-fpm-01.timers.my-controller.post.201.application-json-hal.signin-in.timer
         *
         * Timer metric name:
         * [<timer_prefix>.][<gauge_prefix>.]<controller>.<http-method>.<http-code>.<http-content-type>[.<name>][.<timer_suffix>]
         * For instance: php-fpm-01.gauges.my-controller.post.201.application-json-hal.signin-in.gauge
         */

        // Prefixes
        'counter_prefix' => '',
        'gauge_prefix'   => '',
        'metric_prefix'  => gethostname(),
        'timer_prefix'   => '',

        // Suffixes
        'counter_suffix' => '.count',
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
