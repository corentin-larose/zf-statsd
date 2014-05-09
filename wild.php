<pre><?php
$config = [
    'foo' => [
        'bar' => [
            'baz' => [
               'bat' => [
                    'counter' => false,
                ],
            ],
            '*' => [
               'bat' => [
                    'counter' => true,
                ],
            ],
        ],
    ],
];

$data = [
    'controller'        => 'foo',
    'http-method'       => 'bar',
    'http-code'         => 'baz',
    'http-content-type' => 'bat',
];

foreach ($data as $v) {
    foreach ([$v, '*'] as $key) {
        if (isset($config[$key])) {
            $config[$v][] = $config[$key];
            //unset($config[$key]);
        }
    }
}

print_r($config);
