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
     * @see testOnRoute
     * @return array
     */
    public function configDataProvider()
    {
        return array();
    }

    /**
     * @see testMethodsReturnSelf
     * @return array
     */
    public function methodsReturnSelfDataProvider()
    {
        return array(
            array('setConfig', array(array())),
        );
    }

    public function setUp()
    {
        $this->instance = new StatsdListener();
    }

    /**
     * @coversNothing
     * @dataProvider methodsReturnSelfDataProvider
     *
     * @param string $method
     * @param array  $args
     */
    public function testMethodsReturnsSelf($method, $args)
    {
        $ret = call_user_func_array(array($this->instance, $method), $args);

        $this->assertInstanceOf('\ZF\Statsd\StatsdListener', $ret);
    }
}
