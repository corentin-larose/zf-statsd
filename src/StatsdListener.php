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
     * @return self
     */
    protected function addCounter()
    {
        if (! empty($this->eventConfig['counter'])) {
            $this->metrics[$stat] = "1|c";

            // Sampling
            if (
                (1 > $this->eventConfig['sample_rate'])
                and ((mt_rand() / mt_getrandmax()) > $this->eventConfig['sample_rate'])
            ) {
                $this->metrics[$stat] .= "|@{$this->eventConfig['sample_rate']}";
            }
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function addRamGauge()
    {
        if (! empty($this->eventConfig['ram_gauge'])) {
            /*
             * Since the StatsD module event is called very late in the FINISH
             * event, this should really be the max RAM used for this call.
             */
            $value = memory_get_peak_usage();

            $this->metrics[$stat] = "$value|g";
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function addTimer()
    {
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

            $this->metrics[$stat] = "$time|ms";
        }

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
        $method = strtolower($request->getMethod());

        $response = $e->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }

        $statusCode  = $response->getStatusCode();
        $contentType = strtolower($response->getHeaders()->get('content-type')->getFieldValue());

        $this->resetMetrics()
            ->addCounter()
            ->addRamGauge()
            ->addTimer()
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
