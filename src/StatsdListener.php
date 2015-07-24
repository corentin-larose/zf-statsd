<?php

namespace ZF\Statsd;

use Zend\EventManager\EventInterface;
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
    protected $config = [];

    /**
     * @var array
     */
    protected $eventConfig = [];

    /**
     * @var array
     */
    protected $events = [];

    /**
     * @var array
     */
    protected $metrics = [];

    /**
     * @param string $metricName
     * @param string $value
     *
     * @return self
     */
    protected function addMemory($metricName, $value = null)
    {
        /*
         * Since the StatsD module event is called very late in the FINISH
         * event, this should really be the max RAM used for this call.
         *
         * We use a timer metric type since it can handle whatever number.
         */
        $value or $value = memory_get_peak_usage();

        $value *= 1000;

        $this->metrics[$metricName] = "$value|ms";

        return $this;
    }

    /**
     * @param string $metricName
     *
     * @return self
     */
    protected function addTimer($metricName, $time)
    {
        $time *= 1000;

        $this->metrics[$metricName] = "$time|ms";

        return $this;
    }

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $sem = $events->getSharedManager();
        $this->listeners[] = $sem->attach('*', '*', [$this, 'onEventStart'], 10000);
        $this->listeners[] = $sem->attach('*', '*', [$this, 'onEventEnd'], -10000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, [$this, 'onFinish'], -11000);
    }

    /**
     * @param int $end
     * @param int $start
     *
     * @return int
     */
    protected function getTimeDiff($end, $start = null)
    {
        $start or $start = $_SERVER['REQUEST_TIME_FLOAT'];

        return (microtime(true) - $start);
    }

    public function onEventEnd(EventInterface $e)
    {
        $start = $this->events[$e->getName()]['start'];
        unset($this->events[$e->getName()]['start']);

        $diff = microtime(true) - $start;
        $this->setEvents($e->getName(), 'duration', $diff)
            ->setEvents($e->getName(), 'memory', memory_get_peak_usage());
    }

    public function onEventStart(EventInterface $e)
    {
        // First event just follows boostrap.
        if (empty($this->events[MvcEvent::EVENT_BOOTSTRAP])) {
            $diff = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            $this->setEvents(MvcEvent::EVENT_BOOTSTRAP, 'duration', $diff)
                ->setEvents(MvcEvent::EVENT_BOOTSTRAP, 'memory', memory_get_peak_usage());
        }

        $this->setEvents($e->getName(), 'start', microtime(true));
    }

    public function onFinish(EventInterface $e)
    {
        if (empty($this->config['enable'])) {
            return;
        }

        /* @var $request HttpRequest */
        $request = $e->getRequest();
        if (!$request instanceof HttpRequest) {
            return;
        }

        $response = $e->getResponse();
        if (!$response instanceof HttpResponse) {
            return;
        }

        $diff = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $this->setEvents('total', 'duration', $diff)
            ->setEvents('total', 'memory', memory_get_peak_usage());

        list(
            $memoryMetric,
            $stoppedMetric,
            $timerMetric
        ) = $this->prepareMetricNames($e);

        $this->resetMetrics();

        foreach ($this->events as $event => $data) {
            list($event) = $this->prepareTokens([$event]);

            if (isset($data['duration']) and isset($data['memory'])) {
                $this->addMemory(str_replace('%mvc-event%', $event, $memoryMetric), $data['memory']);
                $this->addTimer(str_replace('%mvc-event%', $event, $timerMetric), $data['duration']);
            } elseif (isset($data['start'])) { // Stopped propagation
                $this->addMemory(str_replace('%mvc-event%', $event, $stoppedMetric), 1);
            }
        }

        $this->resetEvents();

        $this->send();
    }

    /**
     * @param MvcEvent $e
     */
    protected function prepareMetricNames(MvcEvent $e)
    {
        $request = $e->getRequest();
        $response = $e->getResponse();

        $memoryConfig = $this->config['memory_pattern'];
        $stoppedConfig = $this->config['stopped_pattern'];
        $timerConfig = $this->config['timer_pattern'];

        $tokens = [];

        if (
            (false !== strpos($memoryConfig, '%hostname%'))
            or (false !== strpos($stoppedConfig, '%hostname%'))
            or (false !== strpos($timerConfig, '%hostname%'))
        ) {
            $tokens['hostname'] = gethostname();
        }

        if (
            (false !== strpos($memoryConfig, '%controller%'))
            or (false !== strpos($stoppedConfig, '%controller%'))
            or (false !== strpos($timerConfig, '%controller%'))
        ) {
            $tokens['controller'] = $e->getRouteMatch()
                ->getParam('controller');
        }

        if (
            (false !== strpos($memoryConfig, '%http-method%'))
            or (false !== strpos($stoppedConfig, '%http-method%'))
            or (false !== strpos($timerConfig, '%http-method%'))
        ) {
            $tokens['http-method'] = $request->getMethod();
        }

        if (
            (false !== strpos($memoryConfig, '%http-code%'))
            or (false !== strpos($stoppedConfig, '%http-code%'))
            or (false !== strpos($timerConfig, '%http-code%'))
        ) {
            $tokens['http-code'] = $response->getStatusCode();
        }

        if (
            (false !== strpos($memoryConfig, '%response-content-type%'))
            or (false !== strpos($stoppedConfig, '%response-content-type%'))
            or (false !== strpos($timerConfig, '%response-content-type%'))
        ) {
            $headers = $response->getHeaders();
            if ($headers->has('content-type')) {
                $tokens['response-content-type'] = $headers->get('content-type')
                    ->getFieldValue();
            } else {
                $tokens['response-content-type'] = 'no-content-type';
            }
        }

        $tokens = $this->prepareTokens($tokens);

        foreach ($tokens as $k => $v) {
            $memoryConfig = str_replace("%$k%", $v, $memoryConfig);
            $stoppedConfig = str_replace("%$k%", $v, $stoppedConfig);
            $timerConfig = str_replace("%$k%", $v, $timerConfig);
        }

        return [$memoryConfig, $stoppedConfig, $timerConfig];
    }

    /**
     * @param array $tokens
     *
     * @return array
     */
    protected function prepareTokens(array $tokens)
    {
        $regex = empty($this->config['replace_dots_in_tokens'])
            ? '/[^a-z0-9.]+/ui'
            : '/[^a-z0-9]+/ui';

        foreach ($tokens as &$token) {
            $token = preg_replace($regex, $this->config['replace_special_chars_with'], $token);
        }

        if (is_callable($this->config['metric_tokens_callback'])) {
            foreach ($tokens as &$token) {
                $token = call_user_func($this->config['metric_tokens_callback'], $token);
            }
        }

        return $tokens;
    }

    /**
     * @return self
     */
    protected function resetEvents()
    {
        $this->events = [];

        return $this;
    }

    /**
     * @return self
     */
    protected function resetMetrics()
    {
        $this->metrics = [];

        return $this;
    }

    /**
     * Sends the metrics over UDP.
     *
     * @return self
     */
    protected function send()
    {
        try {
            if (!empty($this->metrics)) {
                $fp = fsockopen("{$this->config['statsd']['protocol']}://{$this->config['statsd']['host']}", $this->config['statsd']['port']);

                if (!$fp) {
                    return;
                }

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

    /**
     * Sets config.
     *
     * @param array $config
     *
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @param string $eventName
     * @param string $offset
     * @param mixed  $value
     *
     * @return self
     */
    protected function setEvents($eventName, $offset, $value)
    {
        $this->events[$eventName][$offset] = $value;

        return $this;
    }
}
