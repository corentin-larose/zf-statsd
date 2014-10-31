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
    protected $config = array();

    /**
     * @var array
     */
    protected $eventConfig = array();

    /**
     * @var array
     */
    protected $events = array();

    /**
     * @var array
     */
    protected $metrics = array();

    /**
     * @param  string $metricName
     * @param  string $value
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
     * @param  string $metricName
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
        $this->listeners[] = $events->attach('*', array($this, 'onEventStart'), 10000);
        $this->listeners[] = $events->attach('*', array($this, 'onEventEnd'), -10000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, array($this, 'onFinish'), -11000);
    }

    /**
     * @throws \LogicException
     * @return integer
     */
    protected function getRequestTime()
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $start = $_SERVER["REQUEST_TIME_FLOAT"]; // As of PHP 5.4.0
        } else {
            if (! defined('REQUEST_TIME_FLOAT')) {
                throw new \LogicException("For a PHP version lower than 5.4.0 you MUST call define('REQUEST_TIME_FLOAT', microtime(true)) very early in your boostrap/index.php script in order to use a StatsD timer");
            }
            $start = REQUEST_TIME_FLOAT;
        }

        return $start;
    }

    /**
     * @param  integer $end
     * @param  integer $start
     * @return integer
     */
    protected function getTimeDiff($end, $start = null)
    {
        $start or $start = $this->getRequestTime();

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
            $diff = microtime(true) - $this->getRequestTime();
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
        if (! $request instanceof HttpRequest) {
            return;
        }

        $response = $e->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }

        $diff = microtime(true) - $this->getRequestTime();
        $this->setEvents('total', 'duration', $diff)
            ->setEvents('total', 'memory', memory_get_peak_usage());

        list(
            $memoryMetric,
            $timerMetric
        ) = $this->prepareMetricNames($e);

        $this->resetMetrics();

        foreach ($this->events as $event => $data) {
            if (isset($data['duration']) and isset($data['memory'])) {
                list($event) = $this->prepareTokens(array($event));

                $this->addMemory(str_replace('%mvc-event%', $event, $memoryMetric), $data['memory']);
                $this->addTimer(str_replace('%mvc-event%', $event, $timerMetric), $data['duration']);
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
        $timerConfig  = $this->config['timer_pattern'];

        $tokens = array();

        if (
            (false !== strpos($memoryConfig, '%hostname%'))
            or (false !== strpos($timerConfig, '%hostname%'))
        ) {
            $tokens['hostname'] = gethostname();
        }

        if (
            (false !== strpos($memoryConfig, '%controller%'))
            or (false !== strpos($timerConfig, '%controller%'))
        ) {
            $tokens['controller'] = $e->getRouteMatch()
                ->getParam('controller');
        }

        if (
            (false !== strpos($memoryConfig, '%http-method%'))
            or (false !== strpos($timerConfig, '%http-method%'))
        ) {
            $tokens['http-method'] = $request->getMethod();
        }

        if (
            (false !== strpos($memoryConfig, '%http-code%'))
            or (false !== strpos($timerConfig, '%http-code%'))
        ) {
            $tokens['http-code'] = $response->getStatusCode();
        }

        if (
            (false !== strpos($memoryConfig, '%response-content-type%'))
            or (false !== strpos($timerConfig, '%response-content-type%'))
        ) {
            $tokens['response-content-type'] = $response->getHeaders()
                ->get('content-type')
                ->getFieldValue();
        }

        $tokens = $this->prepareTokens($tokens);

        foreach ($tokens as $k => $v) {
            $memoryConfig = str_replace("%$k%", $v, $memoryConfig);
            $timerConfig  = str_replace("%$k%", $v, $timerConfig);
        }

        return array($memoryConfig, $timerConfig);
    }

    /**
     * @param  array $tokens
     * @return array
     */
    protected function prepareTokens(array $tokens)
    {
        $regex =  empty($this->config['replace_dots_in_tokens'])
            ? '/[^a-z0-9.]+/ui'
            : '/[^a-z0-9]+/ui';

        foreach ($tokens as $k => &$token) {
            $token = preg_replace($regex, $this->config['replace_special_chars_with'], $token);
        }

        if (is_callable($this->config['metric_tokens_callback'])) {
            foreach ($tokens as $k => &$token) {
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
        $this->events = array();

        return $this;
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
     * Sends the metrics over UDP
     *
     * @return self
     */
    protected function send()
    {
        try {
            if (! empty($this->metrics)) {
                $fp = fsockopen("udp://{$this->config['statsd']['host']}", $this->config['statsd']['port']);

                if (! $fp) {
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
     * @param  array $config
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @param  string $eventName
     * @param  string $offset
     * @param  mixed  $value
     * @return self
     */
    protected function setEvents($eventName, $offset, $value)
    {
        $this->events[$eventName][$offset] = $value;

        return $this;
    }
}
