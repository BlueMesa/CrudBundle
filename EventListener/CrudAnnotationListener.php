<?php

/*
 * This file is part of the CRUD Bundle.
 *
 * Copyright (c) 2016 BlueMesa LabDB Contributors <labdb@bluemesa.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bluemesa\Bundle\CrudBundle\EventListener;

use Bluemesa\Bundle\CoreBundle\EventListener\AttributeGeneratorTrait;
use Bluemesa\Bundle\CrudBundle\Controller\Annotations\Action;
use Bluemesa\Bundle\CrudBundle\Controller\Annotations\Controller;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Util\ClassUtils;
use JMS\DiExtraBundle\Annotation as DI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterManager;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * The CrudAnnotationListener handles CRUD annotations for controllers.
 *
 * @DI\Service("bluemesa.crud.listener.annotation")
 * @DI\Tag("kernel.event_listener",
 *     attributes = {
 *         "event" = "kernel.controller",
 *         "method" = "onKernelController",
 *         "priority" = 10
 *     }
 * )
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class CrudAnnotationListener
{
    use AttributeGeneratorTrait;

    /**
     * @var ParamConverterManager
     */
    protected $manager;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "manager" = @DI\Inject("sensio_framework_extra.converter.manager"),
     *     "reader" = @DI\Inject("annotation_reader"),
     *     "router" = @DI\Inject("router")
     * })
     *
     * @param ParamConverterManager $manager  A ParamConverterManager instance
     * @param Reader                $reader   A Reader instance
     * @param RouterInterface       $router   A RouterInterface instance
     */
    public function __construct(ParamConverterManager $manager, Reader $reader, RouterInterface $router)
    {
        $this->manager = $manager;
        $this->reader = $reader;
        $this->router = $router;
    }

    /**
     * Adds CRUD parameters to the Request object.
     *
     * @param FilterControllerEvent $event A FilterControllerEvent instance
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        if (is_array($controller)) {
            $c = new \ReflectionClass(ClassUtils::getClass($controller[0]));
            $m = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && is_callable($controller, '__invoke')) {
            /** @var object $controller */
            $c = new \ReflectionClass(ClassUtils::getClass($controller));
            $m = new \ReflectionMethod($controller, '__invoke');
        } else {
            return;
        }

        /** @var Controller $controllerAnnotation */
        $controllerAnnotation = $this->reader->getClassAnnotation($c, Controller::class);
        /** @var Action $actionAnnotation */
        $actionAnnotation = $this->reader->getMethodAnnotation($m, Action::class);

        if (! $controllerAnnotation) {
            return;
        }

        $class = $this->getEntityClass($controllerAnnotation, $c);
        if ((! $request->attributes->has('entity'))&&($request->attributes->has('id'))) {
            $configurations = array();
            $configuration = new ParamConverter(array());
            $configuration->setName('entity');
            $configuration->setClass($class);
            $configurations['entity'] = $configuration;
            $this->manager->apply($request, $configurations);
        }
        $name = $this->getEntityName($controllerAnnotation, $class);
        $this->addRequestAttribute($request, 'entity_class', $class);
        $this->addRequestAttribute($request, 'entity_name', $name);

        if (! $actionAnnotation) {
            return;
        }

        $type = $this->getFormType($actionAnnotation, $controllerAnnotation);
        $this->addRequestAttribute($request, 'form_type', $type);
    }

    /**
     * @param Controller        $controllerAnnotation
     * @param \ReflectionClass  $c
     *
     * @return string
     * @throws \LogicException
     */
    private function getEntityClass(Controller $controllerAnnotation, \ReflectionClass $c)
    {
        $class = $controllerAnnotation->getEntityClass();
        if (null === $class) {
            $controller = $c->getShortName();
            $name = str_replace("Controller", "", $controller);
            $class = preg_replace('/[\s_]+/', '', $name);
        }
        if (! class_exists($class)) {
            $controllerNamespace = $c->getNamespaceName() . "\\";
            $class = str_replace("\\Controller\\", "\\Entity\\", $controllerNamespace) . $class;
            if (! class_exists($class)) {
                $message  = "Cannot find class ";
                $message .= $controllerAnnotation->getEntityClass();
                $message .= ". Please specify the entity FQCN using entity_class parameter.";
                throw new \LogicException($message);
            }
        }

        return $class;
    }

    /**
     * @param Controller $controllerAnnotation
     * @param $class
     * @return string
     */
    private function getEntityName(Controller $controllerAnnotation, $class)
    {
        $name = $controllerAnnotation->getEntityName();
        if (null === $name) {
            $e = new \ReflectionClass($class);
            $name = preg_replace(array('/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'), ' $0', $e->getShortName());
        }

        return strtolower($name);
    }

    /**
     * @param Controller    $controllerAnnotation
     * @param Action        $actionAnnotation
     * @return string
     * @throws \LogicException
     */
    private function getFormType(Action $actionAnnotation, Controller $controllerAnnotation)
    {
        $type = $actionAnnotation->getFormType();
        if (null === $type) {
            $type = $controllerAnnotation->getFormType();
        }

        return $type;
    }
}
