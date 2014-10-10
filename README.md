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

#### Key: `enable`

The `enable` key is utilized for enabling/disabling the statsd module at run time.
If you no longer need this module, rather consider removing the module from the `application.config.php` list.

Example:

```php
'zf-http-cache' => array(
    /* ... */
    'enable' => true, // Cache module is enabled.
    /* ... */
),    
```

#### Key: `memory_pattern`

The `memory_pattern` key is utilized to configure the metric name pattern for the memory related data.
All token surrounded by `%` will be replaced with the corresponding value (see the available token list below).
You can remove, add and sort tokens at your will.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'memory_pattern' => '%hostname%.%controller%.%http-method%.%http-code%.%response-content-type%.%mvc-event%.memory',
    /* ... */
),    
```

#### Key: `timer_pattern`

The `timer_pattern` key is utilized to configure the metric name pattern for the time related data.
All token surrounded by `%` will be replaced with the corresponding value (see the available token list below).
You can remove, add and sort tokens at your will.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'timer_pattern' => '%hostname%.%controller%.%http-method%.%http-code%.%response-content-type%.%mvc-event%.duration',
    /* ... */
),    
```

##### Token: `%hostname%`

Will be replaced with the value returned by ```hostname()``` function.

##### Token: `%controller%`

Will be replaced with the value returned by ```Zend\Mvc\MvcEvent::getRouteMatch()->getParam('controller')``` method.

##### Token: `%http-method%`

Will be replaced with the value returned by ```Zend\Http\Request::getMethod()``` method.

##### Token: `%http-code%`

Will be replaced with the value returned by ```Zend\Http\Response::getStatusCode()``` method.

##### Token: `%response-content-type%`

Will be replaced with the value returned by ```Zend\Http\Response::getHeaders()->get('content-type')->getFieldValue()``` method.

##### Token: `%mvc-event%`

Will be replaced with the value returned by ```Zend\Mvc\MvcEvent::getName()``` method.

#### Key: `metric_tokens_callback`

Callback used to override the case of the metric names, must be a valid PHP callback.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'metric_tokens_callback' => 'strtolower',
    /* ... */
),    
```

#### Key: `replace_dots_in_tokens`

Whether to override a `.` found in a string used in the metric name.
If true, `.` will be replaced with `replace_special_chars_with`.

Example:

```php
'zf-statsd' => array(
    /* ... */
    'replace_dots_in_tokens' => true,
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
