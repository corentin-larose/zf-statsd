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

    protected $eventConfig = array();

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'), -10000);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param  string|array $stats      The metric(s) to decrement.
     * @param  float|1      $sampleRate the rate (0-1) for sampling.
     * @return boolean
     */
    public function decrement($stats, $sampleRate = 1)
    {
        $this->updateStats($stats, -1, $sampleRate, 'c');

        return $this;
    }

    /**
     * Sets one or more gauges to a value
     *
     * @param string|array $stats The metric(s) to set.
     * @param float        $value The value for the stats.
     */
    public function gauge($stats, $value)
    {
        $this->updateStats($stats, $value, 1, 'g');

        return $this;
    }

    /**
     * Increments one or more stats counters
     *
     * @param  string|array $stats      The metric(s) to increment.
     * @param  float|1      $sampleRate the rate (0-1) for sampling.
     * @return boolean
     */
    public function increment($stats, $sampleRate = 1)
    {
        $this->updateStats($stats, 1, $sampleRate, 'c');

        return $this;
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
    }

    /**
     * A "Set" is a count of unique events.
     * This data type acts like a counter, but supports counting
     * of unique occurences of values between flushes. The backend
     * receives the number of unique events that happened since
     * the last flush.
     *
     * The reference use case involved tracking the number of active
     * and logged in users by sending the current userId of a user
     * with each request with a key of "uniques" (or similar).
     *
     * @param string|array $stats The metric(s) to set.
     * @param float        $value The value for the stats.
     */
    public function set($stats, $value)
    {
        $this->updateStats($stats, $value, 1, 's');

        return $this;
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
        // sampling
        $sampledData = [];

        if ($sampleRate < 1) {
            foreach ($data as $stat => $value) {
                if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                    $sampledData[$stat] = "$value|@$sampleRate";
                }
            }
        } else {
            $sampledData = $data;
        }

        if (empty($sampledData)) { return $this; }

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
    * Sets one or more timing values
    *
    * @param string|array $stats The metric(s) to set.
    * @param float $time The elapsed time (ms) to log
    */
    public function timing($stats, $time)
    {
        $this->updateStats($stats, $time, 1, 'ms');

        return $this;
    }

    /**
     * Updates one or more stats.
     *
     * @param  string|array $stats      The metric(s) to update. Should be either a string or array of metrics.
     * @param  int|1        $delta      The amount to increment/decrement each metric by.
     * @param  float|1      $sampleRate the rate (0-1) for sampling.
     * @param  string|c     $metric     The metric type ("c" for count, "ms" for timing, "g" for gauge, "s" for set)
     * @return boolean
     */
    protected function updateStats($stats, $delta = 1, $sampleRate = 1, $metric = 'c')
    {
        if (!is_array($stats)) {
            $stats = [$stats];
        }

        $data = [];

        foreach ($stats as $stat) {
            $data[$stat] = "$delta|$metric";
        }

        return $this->send($data, $sampleRate);
    }
}
