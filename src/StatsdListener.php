<?php
namespace ZF\Statsd;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\Request as HttpRequest;
use Zend\Http\Response as HttpResponse;
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
     * @param string $metricName
     * @return self
     */
    protected function addMemory($metricName)
    {
        /*
         * Since the StatsD module event is called very late in the FINISH
         * event, this should really be the max RAM used for this call.
         *
         * We use a timer metric type since it can handle whatever number.
         */
        $value = memory_get_peak_usage() * 1000;

        $this->metrics[$metricName] = "$value|ms";

        return $this;
    }

    /**
     * @param string $metricName
     * @return self
     */
    protected function addTimer($metricName)
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $start = $_SERVER["REQUEST_TIME_FLOAT"]; // As of PHP 5.4.0
        } else {
            if (! defined('REQUEST_TIME_FLOAT')) {
                throw new \LogicException("For a PHP version lower than 5.4.0 you MUST call define('REQUEST_TIME_FLOAT', microtime(true)) very early in your boostrap/index.php script in order to use a StatsD timer");
            }
            $start = REQUEST_TIME_FLOAT;
        }

        $time = (microtime(true) - $start) * 1000;

        $this->metrics[$metricName] = "$time|ms";

        return $this;
    }

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'), -10000);
    }

    protected function getMetricNames($controller, $method, $statusCode, $contentType)
    {
        $metricName = array();

        empty($this->config['metric_prefix'])
            or $metricName[] = $this->config['metric_prefix'];
        $metricName[] = $controller;
        $metricName[] = $method;
        $metricName[] = $statusCode;
        $metricName[] = $contentType;

        foreach ($metricName as &$v) {
            if (! empty($this->config['replace_special_chars_with'])) {
                if (! empty($this->config['replace_dots'])) {
                    $v = preg_replace('/[^a-z0-9]+/ui', $this->config['replace_special_chars_with'], $v);
                } else {
                    $v = preg_replace('/[^a-z0-9.]+/ui', $this->config['replace_special_chars_with'], $v);
                }
            }

            if (! empty($this->config['override_case_callback']) and is_callable($this->config['override_case_callback'])) {
                $v = call_user_func($this->config['override_case_callback'], $v);
            }
        }

        $memory   = implode('.', $metricName) . '.' . $this->config['memory_name'];
        $duration = implode('.', $metricName) . '.' . $this->config['timer_name'];

        return array($memory, $duration);
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

        $response = $e->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }

        $controller = $e->getRouteMatch()
            ->getParam('controller');
        $method = strtolower($request->getMethod());

        $statusCode  = $response->getStatusCode();
        $contentType = strtolower($response->getHeaders()->get('content-type')->getFieldValue());

        list(
            $memory,
            $timerName
        ) = $this->getMetricNames($controller, $method, $statusCode, $contentType);

        $this->resetMetrics()
            ->addMemory($memory)
            ->addTimer($timerName)
            ->send();
    }

    /**
     * @return self
     */
    protected function resetMetrics()
    {
        $this->metrics = array();

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
     *
     * @return self
     */
    protected function send()
    {
        try {
            if (! empty($this->metrics)) {
                $fp = fsockopen("udp://{$this->config['statsd']['host']}", $this->config['statsd']['port']);

                if (! $fp) { return; }

                foreach ($this->metrics as $stat => $value) {
                    fwrite($fp, "$stat:$value");
                }

                fclose($fp);

                $this->resetMetrics();
            }
        } catch (\Exception $e) {
            // Ignores failures silently
        }

        return $this;
    }
}
