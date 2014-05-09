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
                            'ram_gauge'        => true,
                            'sample_rate'      => 1,
                            'timer'            => true,
                        ),
                    ),
                ),
            ),
        ),

        // Suffixes
        'counter_suffix' => '.count',
        'gauge_suffix'   => '',
        'timer_suffix'   => '',

        // Chars and case overriding
        'override_case_callback'      => 'strtolower', // strtoupper, strtolower, ucwords, etc.
        'override_dots'               => true,
        'override_special_chars_with' => '-',

        'statsd' => array(
            'host' => '127.0.0.1',
            'port' => '8125',
        ),
    ),
);
