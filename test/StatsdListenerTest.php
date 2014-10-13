<?php
namespace ZFTest\Statsd;

use Zend\Mvc\MvcEvent;
use ZF\Statsd\StatsdListener;

class StatsdListenerTest extends AbstractTestCase
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
     * @see testGetTimeDiff
     * @return array
     */
    public function getTimeDiffDataProvider()
    {
        return array(
            array(microtime(true), microtime(true) - 1),
            array(microtime(true)),
        );
    }

    /**
     * @see testMethodsReturnSelf
     * @return array
     */
    public function methodsReturnSelfDataProvider()
    {
        return array(
            array('addMemory', array(''), true),
            array('addTimer', array('', 1000), true),
            array('resetEvents', array(), true),
            array('resetMetrics', array(), true),
            array('send', array(), true),
            array('setConfig', array(array()), true),
        );
    }

    /**
     * @see testPrepareMetricNames
     * @return array
     */
    public function prepareMetricNamesDataProvider()
    {
        $request  = new \Zend\Http\Request();
        $request->setMethod('POST');

        $response = new \Zend\Http\Response();
        $response->setStatusCode(201)
            ->getHeaders()
            ->addHeaders(array('Content-type' => 'application/hal+json'));

        $event    = new MvcEvent(MvcEvent::EVENT_FINISH);
        $event->setRequest($request)
            ->setResponse($response)
            ->setRouteMatch(new \Zend\Mvc\Router\RouteMatch(array(
                'controller' => 'controller-with.dot.And-dashes',
            )));

        $hostname = preg_replace('/[^a-z0-9.]+/ui', '-', strtolower(gethostname()));

        return array(
            array(
                array(
                    'memory_pattern'             => '%hostname%.%controller%.%http-method%.%http-code%.%response-content-type%.route.memory',
                    'metric_tokens_callback'     => 'strtolower',
                    'replace_dots_in_tokens'     => true,
                    'replace_special_chars_with' => '-',
                    'timer_pattern'              => '%hostname%.%controller%.%http-method%.%http-code%.%response-content-type%.route.duration',
                ),
                $event,
                "$hostname.controller-with-dot-and-dashes.post.201.application-hal-json.route.memory",
                "$hostname.controller-with-dot-and-dashes.post.201.application-hal-json.route.duration",
            ),
            array(
                array(
                    'memory_pattern'             => '%controller%.%http-method%.%http-code%.%response-content-type%.route.memory',
                    'metric_tokens_callback'     => 'strtolower',
                    'replace_dots_in_tokens'     => true,
                    'replace_special_chars_with' => '-',
                    'timer_pattern'              => '%controller%.%http-method%.%http-code%.%response-content-type%.route.duration',
                ),
                $event,
                "controller-with-dot-and-dashes.post.201.application-hal-json.route.memory",
                "controller-with-dot-and-dashes.post.201.application-hal-json.route.duration",
            ),
            array(
                array(
                    'memory_pattern'             => '%hostname%.%http-method%.%http-code%.%response-content-type%.route.memory',
                    'metric_tokens_callback'     => 'strtolower',
                    'replace_dots_in_tokens'     => true,
                    'replace_special_chars_with' => '-',
                    'timer_pattern'              => '%hostname%.%http-method%.%http-code%.%response-content-type%.route.duration',
                ),
                $event,
                "$hostname.post.201.application-hal-json.route.memory",
                "$hostname.post.201.application-hal-json.route.duration",
            ),
            array(
                array(
                    'memory_pattern'             => '%hostname%.%controller%.%http-code%.%response-content-type%.route.memory',
                    'metric_tokens_callback'     => 'strtolower',
                    'replace_dots_in_tokens'     => true,
                    'replace_special_chars_with' => '-',
                    'timer_pattern'              => '%hostname%.%controller%.%http-code%.%response-content-type%.route.duration',
                ),
                $event,
                "$hostname.controller-with-dot-and-dashes.201.application-hal-json.route.memory",
                "$hostname.controller-with-dot-and-dashes.201.application-hal-json.route.duration",
            ),
            array(
                array(
                    'memory_pattern'             => '%hostname%.%controller%.%http-method%.%response-content-type%.route.memory',
                    'metric_tokens_callback'     => 'strtolower',
                    'replace_dots_in_tokens'     => true,
                    'replace_special_chars_with' => '-',
                    'timer_pattern'              => '%hostname%.%controller%.%http-method%.%response-content-type%.route.duration',
                ),
                $event,
                "$hostname.controller-with-dot-and-dashes.post.application-hal-json.route.memory",
                "$hostname.controller-with-dot-and-dashes.post.application-hal-json.route.duration",
            ),
            array(
                array(
                    'memory_pattern'             => '%controller%.%http-method%.%http-code%.ROUTE.memory',
                    'metric_tokens_callback'     => 'strtoupper',
                    'replace_dots_in_tokens'     => false,
                    'replace_special_chars_with' => '-',
                    'timer_pattern'              => '%controller%.%http-method%.%http-code%.ROUTE.duration',
                ),
                $event,
                "CONTROLLER-WITH.DOT.AND-DASHES.POST.201.ROUTE.memory",
                "CONTROLLER-WITH.DOT.AND-DASHES.POST.201.ROUTE.duration",
            ),
        );
    }

    /**
     * @see testPrepareTokens
     * @return array
     */
    public function prepareTokensDataProvider()
    {
        return array(
            array(
                array(
                    'metric_tokens_callback'     => 'strtolower',
                    'replace_dots_in_tokens'     => true,
                    'replace_special_chars_with' => '-',
                ),
                array('controller.with-dot-and-DASHES'),
                array('controller-with-dot-and-dashes'),
            ),
            array(
                array(
                    'metric_tokens_callback'     => 'strtoupper',
                    'replace_dots_in_tokens'     => false,
                    'replace_special_chars_with' => '-',
                ),
                array('controller.with-dot-and-DASHES'),
                array('CONTROLLER.WITH-DOT-AND-DASHES'),
            ),
        );
    }

    public function setUp()
    {
        $this->instance = new StatsdListener();

        parent::setup();
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::addMemory()
     * @dataProvider addMemoryDataProvider
     *
     * @param string  $metricName
     * @param integer $value
     */
    public function testAddMemory($metricName, $value = null)
    {
        $this->getMethod('resetMetrics')->invoke($this->instance);
        $this->getMethod('addMemory')->invoke($this->instance, $metricName, $value);

        $metrics = $this->getProperty('metrics');

        if ($value) {
            $this->assertSame(($value * 1000).'|ms', $metrics[$metricName]);
        } else {
            $this->assertRegExp('/[0-9]+|ms/', $metrics[$metricName]);
        }
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::addTimer()
     * @dataProvider addTimerDataProvider
     *
     * @param string  $metricName
     * @param integer $value
     */
    public function testAddTimer($metricName, $value)
    {
        $this->getMethod('resetMetrics')->invoke($this->instance);
        $this->getMethod('addTimer')->invoke($this->instance, $metricName, $value);

        $metrics = $this->getProperty('metrics');
        $this->assertSame(($value * 1000).'|ms', $metrics[$metricName]);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::getRequestTime()
     */
    public function testGetRequestTime()
    {
        if (version_compare('5.4.0', PHP_VERSION) >= 0) {
            $this->setExpectedException('\LogicException');
        }

        $requestTime = $this->getMethod('getRequestTime')->invoke($this->instance);
        $this->assertInternalType('float', $requestTime);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::getTimeDiff()
     * @dataProvider getTimeDiffDataProvider
     *
     * @param integer $end
     * @param integer $start
     * @param integer $exDiff
     */
    public function testGetTimeDiff($end, $start = null)
    {
        if (! $start and version_compare('5.4.0', PHP_VERSION) >= 0) {
            $this->setExpectedException('\LogicException');
        }

        $diff = $this->getMethod('getTimeDiff')->invoke($this->instance, $end, $start);
        $this->assertInternalType('float', $diff);
    }

    /**
     * @coversNothing
     * @dataProvider methodsReturnSelfDataProvider
     *
     * @param string $method
     * @param array  $args
     */
    public function testMethodsReturnSelf($method, $args, $protected)
    {
        if ($protected) {
            $ret = $this->getMethod($method)->invokeArgs($this->instance, $args);
        } else {
            $ret = call_user_func_array(array($this->instance, $method), $args);
        }

        $this->assertInstanceOf('\ZF\Statsd\StatsdListener', $ret);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::onEventEnd()
     */
    public function testOnEventEnd()
    {
        $event = new MvcEvent(MvcEvent::EVENT_FINISH);

        $metrics = $this->getMethod('setEvents')->invoke($this->instance, MvcEvent::EVENT_FINISH, 'start', microtime(true));
        $this->instance->onEventEnd($event);
        $events = $this->getProperty('events');

        $this->assertArrayHasKey('duration', $events[MvcEvent::EVENT_FINISH]);
        $this->assertInternalType('float', $events[MvcEvent::EVENT_FINISH]['duration']);
        $this->assertArrayHasKey('memory', $events[MvcEvent::EVENT_FINISH]);
        $this->assertInternalType('integer', $events[MvcEvent::EVENT_FINISH]['memory']);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::onEventStart()
     */
    public function testOnEventStart()
    {
        if (version_compare('5.4.0', PHP_VERSION) >= 0) {
            $this->setExpectedException('\LogicException');
        }

        $event = new MvcEvent(MvcEvent::EVENT_ROUTE);

        $this->instance->onEventStart($event);
        $events = $this->getProperty('events');

        $this->assertArrayHasKey('duration', $events[MvcEvent::EVENT_BOOTSTRAP]);
        $this->assertInternalType('float', $events[MvcEvent::EVENT_BOOTSTRAP]['duration']);
        $this->assertArrayHasKey('memory', $events[MvcEvent::EVENT_BOOTSTRAP]);
        $this->assertInternalType('integer', $events[MvcEvent::EVENT_BOOTSTRAP]['memory']);
        $this->assertArrayHasKey('start', $events[MvcEvent::EVENT_ROUTE]);
        $this->assertInternalType('float', $events[MvcEvent::EVENT_ROUTE]['start']);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::onFinish()
     */
    public function testOnFinish()
    {
        if (version_compare('5.4.0', PHP_VERSION) >= 0) {
            $this->setExpectedException('\LogicException');
        }

        $event = new MvcEvent(MvcEvent::EVENT_FINISH);

        $this->instance->onFinish($event);

        $this->assertEmpty($this->getProperty('events'));
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
    public function testPrepareMetricNames(array $config, MvcEvent $e, $exMemoryConfig, $exTimerConfig)
    {
        $this->instance
            ->setConfig($config);

        list(
            $memoryConfig,
            $timerConfig
        ) = $this->getMethod('prepareMetricNames')
                ->invoke($this->instance, $e);

        $this->assertSame($exMemoryConfig, $memoryConfig);
        $this->assertSame($exTimerConfig, $timerConfig);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::prepareTokens()
     * @dataProvider prepareTokensDataProvider
     *
     * @param string $expectedTokens
     */
    public function testPrepareTokens(array $config, array $tokens, array $expectedTokens)
    {
        $this->instance
            ->setConfig($config);

        $actualTokens = $this->getMethod('prepareTokens')
                ->invoke($this->instance, $tokens);

        $this->assertSame($expectedTokens, $actualTokens);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::resetEvents()
     */
    public function testResetEvents()
    {
        $this->getMethod('setEvents')
            ->invoke($this->instance, MvcEvent::EVENT_FINISH, 'start', microtime(true));

        $this->getMethod('resetEvents')
            ->invoke($this->instance);
        $this->assertEmpty($this->getProperty('events'));
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::resetMetrics()
     */
    public function testResetMetrics()
    {
        $this->setProperty('metrics', array('foo' => 'bar'));
        $this->getMethod('resetMetrics')->invoke($this->instance);
        $this->assertEmpty($this->getproperty('metrics'));
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

        $metrics = array(
            'metric_name1' => 'metric_payload1',
            'metric_name2' => 'metric_payload2',
        );
        $this->setProperty('metrics', $metrics);

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

        $this->assertSame($metrics, $this->getProperty('metrics'));
        $this->getMethod('send')->invoke($this->instance);

        // Reads from UDP socket
        $from = '';
        $port = 0;

        socket_recvfrom($sock, $buf, 512, 0, $from, $port);
        $this->assertSame($buf, 'metric_name1:metric_payload1');

        socket_recvfrom($sock, $buf, 512, 0, $from, $port);
        $this->assertSame($buf, 'metric_name2:metric_payload2');

        socket_close($sock);

        $this->assertEmpty($this->getProperty('metrics'));
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::setConfig()
     */
    public function testSetConfig()
    {
        $config = array('foo' => 'bar');
        $this->instance->setConfig($config);
        $this->assertSame($config, $this->getProperty('config'));
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::setEvents()
     */
    public function testSetEvents()
    {
        $this->getMethod('setEvents')->invoke($this->instance, MvcEvent::EVENT_FINISH, 'start', 1234);
        $this->assertSame(array(MvcEvent::EVENT_FINISH => array('start' => 1234)), $this->getProperty('events'));
    }
}
