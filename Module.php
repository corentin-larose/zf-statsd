<?php

namespace ZF\Statsd;

class Module
{
    public function getAutoloaderConfig()
    {
        return [\Zend\Loader\StandardAutoloader::class => ['namespaces' => [
            __NAMESPACE__ => __DIR__.'/src/',
        ]]];
    }

    public function getConfig()
    {
        return include __DIR__.'/config/module.config.php';
    }

    public function onBootstrap(\Zend\Mvc\MvcEvent $e)
    {
        $app = $e->getApplication();
        $em = $app->getEventManager();
        $sm = $app->getServiceManager();

        $em->attachAggregate($sm->get(\ZF\Statsd\StatsdListener::class));
    }
}
