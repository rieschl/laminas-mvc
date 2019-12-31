<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Service;

use Laminas\EventManager\EventManager;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @covers \Laminas\Mvc\Service\ServiceManagerConfig
 */
class ServiceManagerConfigTest extends TestCase
{
    /**
     * @var ServiceManagerConfig
     */
    private $config;

    /**
     * @var ServiceManager
     */
    private $services;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->config = new ServiceManagerConfig();
        $this->services = new ServiceManager();
        $this->config->configureServiceManager($this->services);
    }

    /**
     * @group 3786
     */
    public function testEventManagerAwareInterfaceIsNotInjectedIfPresentButSharedManagerIs()
    {
        $events = new EventManager();
        TestAsset\EventManagerAwareObject::$defaultEvents = $events;

        $this->services->setInvokableClass('EventManagerAwareObject', __NAMESPACE__ . '\TestAsset\EventManagerAwareObject');

        $instance = $this->services->get('EventManagerAwareObject');
        $this->assertInstanceOf(__NAMESPACE__ . '\TestAsset\EventManagerAwareObject', $instance);
        $this->assertSame($events, $instance->getEventManager());
        $this->assertSame($this->services->get('SharedEventManager'), $events->getSharedManager());
    }

    /**
     * @group 6266
     */
    public function testCanMergeCustomConfigWithDefaultConfig()
    {
        $custom = array(
            'invokables' => array(
                'foo' => '\stdClass',
            ),
            'factories' => array(
                'bar' => function () {
                    return new \stdClass();
                },
            ),
        );

        $config = new ServiceManagerConfig($custom);
        $sm = new ServiceManager();
        $config->configureServiceManager($sm);

        $this->assertTrue($sm->has('foo'));
        $this->assertTrue($sm->has('bar'));
        $this->assertTrue($sm->has('ModuleManager'));
    }

    /**
     * @group 6266
     */
    public function testCanOverrideDefaultConfigWithCustomConfig()
    {
        $custom = array(
            'invokables' => array(
                'foo' => '\stdClass',
            ),
            'factories' => array(
                'ModuleManager' => function () {
                    return new \stdClass();
                },
            ),
        );

        $config = new ServiceManagerConfig($custom);
        $sm = new ServiceManager();
        $config->configureServiceManager($sm);

        $this->assertTrue($sm->has('foo'));
        $this->assertTrue($sm->has('ModuleManager'));

        $this->assertInstanceOf('stdClass', $sm->get('ModuleManager'));
    }

    /**
     * @group 6266
     */
    public function testCanAddDelegators()
    {
        $config = array(
            'invokables' => array(
                'foo' => '\stdClass',
            ),
            'delegators' => array(
                'foo' => array(
                    function (ServiceLocatorInterface $serviceLocator, $name, $requestedName, $callback) {
                        $service = $callback();
                        $service->bar = 'baz';

                        return $service;
                    },
                )
            ),
        );

        $config = new ServiceManagerConfig($config);
        $sm = new ServiceManager();
        $config->configureServiceManager($sm);

        $std = $sm->get('foo');
        $this->assertInstanceOf('stdClass', $std);
        $this->assertEquals('baz', $std->bar);
    }

    /**
     * @group 6266
     */
    public function testDefinesServiceManagerService()
    {
        $this->assertSame($this->services, $this->services->get('ServiceManager'));
    }

    /**
     * @group 6266
     */
    public function testCanOverrideServiceManager()
    {
        $test           = $this;
        $serviceManager = new ServiceManager(new ServiceManagerConfig(array(
            'factories' => array(
                'ServiceManager' => function () use ($test) {
                    return $test;
                }
            ),
        )));

        $this->assertSame($this, $serviceManager->get('ServiceManager'));
    }

    /**
     * @group 6266
     */
    public function testServiceManagerInitializerIsUsedForServiceManagerAwareObjects()
    {
        $instance = $this->getMock('Laminas\ServiceManager\ServiceManagerAwareInterface');

        $instance->expects($this->once())->method('setServiceManager')->with($this->services);

        $this->services->setFactory(
            'service-manager-aware',
            function () use ($instance) {
                return $instance;
            }
        );

        $this->services->get('service-manager-aware');
    }

    /**
     * @group 6266
     */
    public function testServiceManagerInitializerCanBeReplaced()
    {
        $instance       = $this->getMock('Laminas\ServiceManager\ServiceManagerAwareInterface');
        $initializer    = $this->getMock('stdClass', array('__invoke'));
        $serviceManager = new ServiceManager(new ServiceManagerConfig(array(
            'initializers' => array(
                'ServiceManagerAwareInitializer' => $initializer
            ),
            'factories' => array(
                'service-manager-aware' => function () use ($instance) {
                    return $instance;
                },
            ),
        )));

        $initializer->expects($this->once())->method('__invoke')->with($instance, $serviceManager);
        $instance->expects($this->never())->method('setServiceManager');

        $serviceManager->get('service-manager-aware');
    }

    /**
     * @group 6266
     */
    public function testServiceLocatorInitializerIsUsedForServiceLocatorAwareObjects()
    {
        $instance = $this->getMock('Laminas\ServiceManager\ServiceLocatorAwareInterface');

        $instance->expects($this->once())->method('setServiceLocator')->with($this->services);

        $this->services->setFactory(
            'service-locator-aware',
            function () use ($instance) {
                return $instance;
            }
        );

        $this->services->get('service-locator-aware');
    }

    /**
     * @group 6266
     */
    public function testServiceLocatorInitializerCanBeReplaced()
    {
        $instance       = $this->getMock('Laminas\ServiceManager\ServiceLocatorAwareInterface');
        $initializer    = $this->getMock('stdClass', array('__invoke'));
        $serviceManager = new ServiceManager(new ServiceManagerConfig(array(
            'initializers' => array(
                'ServiceLocatorAwareInitializer' => $initializer
            ),
            'factories' => array(
                'service-locator-aware' => function () use ($instance) {
                    return $instance;
                },
            ),
        )));

        $initializer->expects($this->once())->method('__invoke')->with($instance, $serviceManager);
        $instance->expects($this->never())->method('setServiceLocator');

        $serviceManager->get('service-locator-aware');
    }

    /**
     * @group 6266
     */
    public function testEventManagerInitializerCanBeReplaced()
    {
        $instance       = $this->getMock('Laminas\EventManager\EventManagerAwareInterface');
        $initializer    = $this->getMock('stdClass', array('__invoke'));
        $serviceManager = new ServiceManager(new ServiceManagerConfig(array(
            'initializers' => array(
                'EventManagerAwareInitializer' => $initializer
            ),
            'factories' => array(
                'event-manager-aware' => function () use ($instance) {
                    return $instance;
                },
            ),
        )));

        $initializer->expects($this->once())->method('__invoke')->with($instance, $serviceManager);
        $instance->expects($this->never())->method('getEventManager');
        $instance->expects($this->never())->method('setEventManager');

        $serviceManager->get('event-manager-aware');
    }
}
