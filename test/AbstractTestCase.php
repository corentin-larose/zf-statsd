<?php
namespace ZFTest\Statsd;

class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Object
     */
    protected $instance;

    /**
     * @var \ReflectionClass
     */
    protected $reflection;

    public function getMethod($method)
    {
        $method = $this->reflection->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }

    public function getProperty($property)
    {
        $property = $this->reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($this->instance);
    }

    public function setProperty($property, $value)
    {
        $property = $this->reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->setValue($this->instance, $value);
    }

    public function setUp()
    {
        $this->reflection = new \ReflectionClass($this->instance);
    }
}
