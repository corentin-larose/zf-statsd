Zf Statsd
=========

[![Build Status](https://travis-ci.org/corentin-larose/zf-statsd.png)](https://travis-ci.org/corentin-larose/zf-statsd)

Introduction
------------

`zf-statsd` is a ZF2 module for automating monitoring/profiling tasks within a Zend Framework 2 with StatsD.

Installation
------------

Run the following `composer` command:

```console
$ composer require "corentin-larose/zf-statsd:dev-master"
```

Alternately, manually add the following to your `composer.json`, in the `require` section:

```javascript
"require": {
    "corentin-larose/zf-statsd": "dev-master"
}
```

And then run `composer update` to ensure the module is installed.

Finally, add the module name to your project's `config/application.config.php` under the `modules`
key:


```php
return array(
    /* ... */
    'modules' => array(
        /* ... */
        'ZF\Statsd',
    ),
    /* ... */
);
```

Configuration
-------------

### User Configuration

**As a rule of thumb, avoid as much as possible using anonymous functions since it prevents you from caching your configuration.**

The top-level configuration key for user configuration of this module is `zf-statsd`.

The `config/module.config.php` file contains a self-explanative example of configuration.

#### Key: `controllers`

The `controllers` key is utilized for mapping a combination of a route, a HTTP method, a HTTP code and a Content-type (see below) to a monitoring/profiling configuration.

Example:

```php
// See the `config/application.config.php` for a complete commented example
'zf-statsd' => array(
    /* ... */
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
    /* ... */
),    
```

##### Key: `<controller>` 

Either a controller name (as returned by `Zend\Mvc\MvcEvent::getRouteMatch()->getParam('controller')`, case-sensitive) or a wildcard.
A wildcard stands for all the non-specified controllers.

##### Key: `<http-method>` 

Either a lower cased HTTP method (`get`, `post`, etc.) (as returned by `Zend\Http\Request::getMethod()`) or a wildcard.
A wildcard stands for all the non-specified HTTP methods.

##### Key: `<http-code>` 

Either a HTTP status code (`200`, `404`, etc.) (as returned by `Zend\Http\Response::getStatusCode()`) or a wildcard.
A wildcard stands for all the non-specified HTTP status codes.

##### Key: `<http-content-type>` 

Either a HTTP header Content-type value (`application/json`, etc.) (as returned by `Zend\Http\Header\ContentType::getFieldValue()`) or a wildcard.
A wildcard stands for all the non-specified content types.

##### Key: `counter` 

##### Key: `ram_gauge` 

##### Key: `sample_rate` 

##### Key: `timer` 

#### Key: `counter_suffix`

#### Key: `gauge_suffix`

#### Key: `timer_suffix`

#### Key: `override_case_callback`

Callback used to override the case of the metric names.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'override_case_callback' => 'strtolower',
    /* ... */
),    
```

#### Key: `replace_dots`

Whether to override a `.` found in a string used in the metric name.
If true, `.` will be replaced with `replace_special_chars_with`.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'replace_dots' => true,
    /* ... */
),    
```

#### Key: `replace_special_chars_with`

Char used to replace all special chars encountererd within a string used in the metric name.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'replace_special_chars_with' => '-',
    /* ... */
),    
```

#### Key: `statsd`

Statsd host and port.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'statsd' => array(
        'host' => '127.0.0.1',
        'port' => '8125',
    ),
    /* ... */
),    
```

ZF2 Events
----------

### Listeners

#### `ZF\Statsd\StatsdListener`

This listener is attached to the `MvcEvent::EVENT_FINISH` event with the very low priority of `-10000`.
