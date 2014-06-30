<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'ZF\Statsd\StatsdListener' => 'ZF\Statsd\StatsdListenerFactory',
        )
    ),

    'zf-statsd' => array(
        /*
         * Memory metric name:
         * [<metric_prefix>.][<memory_prefix>.]<controller>.<http-method>.<http-code>.<request-http-content-type>.<response-http-content-type>.<mvc-event>[.<memory_suffix>]
         * For instance: my-controller.post.201.application-json.application-json-hal.boostrap
         *
         * Timer metric name:
         * [<metric_prefix>.][<timer_prefix>.]<controller>.<http-method>.<http-code>.<request-http-content-type>.<response-http-content-type>.<mvc-event>[.<timer_suffix>]
         * For instance: my-controller.post.201.application-json.application-json-hal.boostrap
         */

        /*
         * Whether to enable http cache.
         */
        'enable' => true,

        // Prefixes
        'memory_prefix'  => '',
        'metric_prefix'  => gethostname(),
        'timer_prefix'   => '',

        // Suffixes
        'counter_suffix' => 'count',
        'memory_suffix'  => '',
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
