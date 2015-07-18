<?php

namespace ZF\Statsd;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class StatsdListenerFactory implements FactoryInterface
{
    /**
     * @param ServiceLocatorInterface $services
     *
     * @return StatsdListener
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = [];
        if ($serviceLocator->has('Config')) {
            $config = $serviceLocator->get('Config');
            if (isset($config['zf-statsd'])) {
                $config = $config['zf-statsd'];
            }
        }

        $statsdListener = new StatsdListener();
        $statsdListener->setConfig($config);

        return $statsdListener;
    }
}
