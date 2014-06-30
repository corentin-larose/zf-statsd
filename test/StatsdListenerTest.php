<?php
namespace ZFTest\Statsd;

use ZF\Statsd\StatsdListener;

class StatsdListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var StatsdListener
     */
    protected $instance;

    /**
     * @see testMethodsReturnSelf
     * @return array
     */
    public function methodsReturnSelfDataProvider()
    {
        return array(
            array('addMemory', array('')),
            array('addTimer', array('')),
            array('resetMetrics', array('')),
            array('send', array('')),
            array('setConfig', array(array(''))),
        );
    }

    public function setUp()
    {
        $this->instance = new Wrapper();
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::addMemory()
     */
    public function testAddMemory()
    {
        $this->instance->resetMetrics();
        $this->instance->addMemory('metric_name');

        $metrics = $this->instance->getMetrics();
        $this->assertRegExp('/[0-9]+|ms/', $metrics['metric_name']);
    }

    /**
     * @covers \ZF\Statsd\StatsdListener::addTimer()
     */
    public function testAddTimer()
    {
        $this->instance->resetMetrics();
        $this->instance->addMemory('metric_name');

        $metrics = $this->instance->getMetrics();
        $this->assertRegExp('/[0-9]+|ms/', $metrics['metric_name']);
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
     * @covers \ZF\Statsd\StatsdListener::onFinish()
     */
    public function testOnFinish()
    {
        $this->markTestIncomplete();
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
            'metric_name' => 'metric_payload',
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
        socket_close($sock);

        $this->assertSame($buf, 'metric_name:metric_payload');
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
    public function getMetrics()
    {
        return $this->metrics;
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
