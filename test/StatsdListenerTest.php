<?php
namespace ZFTest\Statsd;

use ZF\Statsd\StatsdListener;
use Zend\Mvc\MvcEvent;

class StatsdListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var StatsdListener
     */
    protected $instance;

    /**
     * @see testAddMemory
     * @return array
     */
    public function addMemoryDataProvider()
    {
        return array(
            array('metric_name'),
            array('metric_name', 5),
        );
    }

    /**
     * @see testAddTimer
     * @return array
     */
    public function addTimerDataProvider()
    {
        return array(
            array('metric_name', 100),
            array('metric_name', 5),
        );
    }

    /**
     * @see testMethodsReturnSelf
     * @return array
     */
    public function methodsReturnSelfDataProvider()
    {
        return array(
            array('addMemory', array('')),
            array('addTimer', array('', 1000)),
            array('resetEvents', array()),
            array('resetMetrics', array()),
            array('send', array()),
            array('setConfig', array(array())),
        );
    }

    /**
     * @see testPrepareMetricNames
     * @return array
     */
    public function prepareMetricNamesDataProvider()
    {
        return array(
            array(
                new MvcEvent('route'),
                '%hostname%.%module%.%controller%.%http-method%.%http-code%.%request-content-type%.%response-content-type%.%mvc-event%.memory',
                '%hostname%.%module%.%controller%.%http-method%.%http-code%.%request-content-type%.%response-content-type%.%mvc-event%.duration',
                hostname() . '.',
                hostname() . '.',
            ),
            array(
                new MvcEvent(),
                '%hostname%.%module%.%controller%.%http-method%.%http-code%.%request-content-type%.%response-content-type%.%mvc-event%.memory',
                '%hostname%.%module%.%controller%.%http-method%.%http-code%.%request-content-type%.%response-content-type%.%mvc-event%.duration',
                hostname() . '.',
                hostname() . '.',
            ),
        );
    }

    public function setUp()
    {
        $this->instance = new Wrapper();
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::addMemory()
     * @dataProvider addMemoryDataProvider
     *
     * @param string $metricName
     * @param integer $value
     */
    public function testAddMemory($metricName, $value = null)
    {
        $this->instance->resetMetrics();
        $this->instance->addMemory($metricName, $value);

        $metrics = $this->instance->getMetrics();

        if ($value) {
            $this->assertSame(($value * 1000) . '|ms', $metrics[$metricName]);
        } else {
            $this->assertRegExp('/[0-9]+|ms/', $metrics[$metricName]);
        }
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::addTimer()
     * @dataProvider addTimerDataProvider
     *
     * @param string $metricName
     * @param integer $value
     */
    public function testAddTimer($metricName, $value)
    {
        $this->instance->resetMetrics();
        $this->instance->addMemory($metricName, $value);

        $metrics = $this->instance->getMetrics();
        $this->assertSame(($value * 1000) . '|ms', $metrics[$metricName]);
    }

    /**
     * @coversNothing
     * @dataProvider methodsReturnSelfDataProvider
     *
     * @param string $method
     * @param array  $args
     */
    public function testMethodsReturnSelf($method, $args)
    {
        $ret = call_user_func_array(array($this->instance, $method), $args);

        $this->assertInstanceOf('\ZF\Statsd\StatsdListener', $ret);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::onEventEnd()
     */
    public function testOnEventEnd()
    {
        $this->markTestIncomplete();
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::onEventStart()
     */
    public function testOnEventStart()
    {
        $this->markTestIncomplete();
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::onFinish()
     */
    public function testOnFinish()
    {
        $this->markTestIncomplete();
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::prepareMetricNames()
     * @dataProvider prepareMetricNamesDataProvider
     *
     * @param MvcEvent $e
     * @param string   $memoryConfig
     * @param string   $timerConfig
     * @param string   $exMemoryConfig
     * @param string   $exTimerConfig
     */
    public function testPrepareMetricNames(MvcEvent $e, $memoryConfig, $timerConfig, $exMemoryConfig, $exTimerConfig)
    {
        foreach ($events as $event) {
            list(
                $memoryConfig,
                $timerConfig
             ) = $this->instance->prepareMetricNames($e);

            $this->assertSame($exMemoryConfig, $memoryConfig);
            $this->assertSame($exTimerConfig, $timerConfig);
        }
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::resetEvents()
     */
    public function testResetEvents()
    {
        $this->instance->setEvents(array('foo' => 'bar'));
        $this->instance->resetEvents();
        $this->assertEmpty($this->instance->getEvents());
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::resetMetrics()
     */
    public function testResetMetrics()
    {
        $this->instance->setMetrics(array('foo' => 'bar'));
        $this->instance->resetMetrics();
        $this->assertEmpty($this->instance->getMetrics());
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::send()
     */
    public function testSend()
    {
        $config = array(
            'statsd' => array(
                'host' => '127.0.0.1',
                'port' => '1234',
            ),
        );
        $this->instance->setConfig($config);

        $metrics = [
            'metric_name1'  => 'metric_payload1',
            'metric_name2' => 'metric_payload2',
        ];
        $this->instance->setMetrics($metrics);

        // Creates a UDP socket
        if (! ($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
            $errorcode = socket_last_error();
            $errormsg  = socket_strerror($errorcode);

            $this->fail("Couldn't create socket: [$errorcode] $errormsg");
        }

        // Binds the source address
        if (! socket_bind($sock, $config['statsd']['host'], $config['statsd']['port'])) {
            $errorcode = socket_last_error();
            $errormsg  = socket_strerror($errorcode);

            $this->fail("Could not bind socket: [$errorcode] $errormsg");
        }

        $this->assertSame($metrics, $this->instance->getMetrics());
        $this->instance->send();

        // Reads from UDP socket
        $from = '';
        $port = 0;

        socket_recvfrom($sock, $buf, 512, 0, $from, $port);
        $this->assertSame($buf, 'metric_name1:metric_payload1');

        socket_recvfrom($sock, $buf, 512, 0, $from, $port);
        $this->assertSame($buf, 'metric_name2:metric_payload2');

        socket_close($sock);

        $this->assertEmpty($this->instance->getMetrics());
    }
}

class Wrapper extends StatsdListener
{
    public function __call($method, $args)
    {
        if (! method_exists($this, $method)) {
            throw new \LogicException("Unknown method '$method'");
        }

        return call_user_func_array([$this, $method], $args);
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @return array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @param array $events
     */
    public function setEvents(array $events)
    {
        $this->events = $events;
    }

    /**
     * @param array $metrics
     */
    public function setMetrics(array $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * @param array $eventConfig
     */
    public function setEventConfig(array $eventConfig)
    {
        $this->eventConfig = $eventConfig;
    }
}
