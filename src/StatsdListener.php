<?php
namespace ZF\Statsd;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\Request as HttpRequest;
use Zend\Mvc\MvcEvent;

class StatsdListener extends AbstractListenerAggregate
{
    /**
     * @var array
     */
    protected $config = array();

    /**
     * @var array
     */
    protected $eventConfig = array();

    /**
     * @var array
     */
    protected $metrics = array();

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'), -10000);
    }

    /**
     * @param MvcEvent $e
     */
    public function onFinish(MvcEvent $e)
    {
        if (empty($this->config['enable'])) {
            return;
        }

        /* @var $request HttpRequest */
        $request = $e->getRequest();
        if (! $request instanceof HttpRequest) {
            return;
        }

        if (! empty($this->config['controllers'])) {
            $cacheConfig = $this->config['controllers'];
        } else {
            $this->eventConfig = array();

            return;
        }

        $controller = $e->getRouteMatch()
            ->getParam('controller');

        if (! empty($cacheConfig[$controller])) {
            $controllerConfig = $cacheConfig[$controller];
        } elseif (! empty($cacheConfig['*'])) {
            $controllerConfig = $cacheConfig['*'];
        } else {
            $this->eventConfig = array();

            return;
        }

        $method = strtolower($request->getMethod());

        if (! empty($controllerConfig[$method])) {
            $methodConfig = $controllerConfig[$method];
        } elseif (! empty($controllerConfig['*'])) {
            $methodConfig = $controllerConfig['*'];
        } elseif (! empty($cacheConfig['*'][$method])) {
            $methodConfig = $cacheConfig['*'][$method];
        } elseif (! empty($cacheConfig['*']['*'])) {
            $methodConfig = $cacheConfig['*']['*'];
        } else {
            $this->eventConfig = array();

            return;
        }

        $this->eventConfig = $methodConfig;

        // Sampling
        if (1 > $this->eventConfig['sample_rate']) {
            if ((mt_rand() / mt_getrandmax()) > $this->eventConfig['sample_rate']) {
                return;
            }
        }

        // Counting events
        if (! empty($this->eventConfig['counter'])) {
            $this->updateStats($stats, 1, 'c');
        }

        // RAM gauge
        if (! empty($this->eventConfig['ram_gauge'])) {
            /*
             * Since the StatsD module event is called very late in the FINISH
             * event, this should really be the max RAM used for this call.
             */
            $this->updateStats($stats, memory_get_peak_usage(), 'g');
        }

        // Profiling (timer)
        if (! empty($this->eventConfig['timer'])) {
            if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                $start = $_SERVER["REQUEST_TIME_FLOAT"]; // As of PHP 5.4.0
            } else {
                if (! defined('REQUEST_TIME_FLOAT')) {
                    throw new \LogicException("For a PHP version lower than 5.4.0 you MUST call define('REQUEST_TIME_FLOAT', microtime(true)) very early in your boostrap/index.php script in order to use a StatsD timer");
                }
                $start = REQUEST_TIME_FLOAT;
            }

            $time = (microtime(true) - $start) * 1000;

            $this->updateStats($stats, $time, 'ms');
        }

        $this->send();
    }

    /**
     * Sets config.
     *
     * @param  array $config
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Sends the metrics over UDP
     */
    public function send($data, $sampleRate = 1)
    {
        try {
            $fp = fsockopen("udp://{$this->config['statsd']['host']}", $this->config['statsd']['port']);

            if (! $fp) { return; }

            foreach ($sampledData as $stat => $value) {
                fwrite($fp, "$stat:$value");
            }

            fclose($fp);
        } catch (\Exception $e) {
            // Ignores failures silently
        }

        return $this;
    }

    /**
     * Updates one or more stats.
     *
     * @param  string|array $stats      The metric(s) to update. Should be either a string or array of metrics.
     * @param  int          $delta      The amount to increment/decrement each metric by.
     * @param  string       $metric     The metric type ("c" for count, "ms" for timing, "g" for gauge, "s" for set)
     * @return boolean
     */
    protected function updateStats($stats, $delta, $metric)
    {
        if (!is_array($stats)) {
            $stats = [$stats];
        }

        $data = [];

        foreach ($stats as $stat) {
            $data[$stat] = "$delta|$metric";
        }

        return $this;
    }
}
