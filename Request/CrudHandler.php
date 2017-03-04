<?php

/*
 * This file is part of the CRUD Bundle.
 * 
 * Copyright (c) 2016 BlueMesa LabDB Contributors <labdb@bluemesa.eu>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Bluemesa\Bundle\CrudBundle\Request;


use Bluemesa\Bundle\CoreBundle\Entity\Entity;
use Bluemesa\Bundle\CoreBundle\EventListener\RoutePrefixTrait;
use Bluemesa\Bundle\CoreBundle\Request\AbstractHandler;
use Bluemesa\Bundle\CoreBundle\Request\FormHandlerTrait;
use Bluemesa\Bundle\CrudBundle\Event\CrudControllerEvents;
use Bluemesa\Bundle\CrudBundle\Event\DeleteActionEvent;
use Bluemesa\Bundle\CrudBundle\Event\EditActionEvent;
use Bluemesa\Bundle\CrudBundle\Event\EntityEvent;
use Bluemesa\Bundle\CrudBundle\Event\IndexActionEvent;
use Bluemesa\Bundle\CrudBundle\Event\NewActionEvent;
use Bluemesa\Bundle\CrudBundle\Event\ShowActionEvent;
use FOS\RestBundle\View\View;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CrudHandler
 *
 * @DI\Service("bluemesa.crud.handler")
 *
 * @package Bluemesa\Bundle\CrudBundle\Request
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class CrudHandler extends AbstractHandler
{
    use RoutePrefixTrait;
    use FormHandlerTrait;

    /**
     * This method calls a proper handler for the incoming request
     *
     * @param Request $request
     *
     * @return View
     * @throws \LogicException
     */
    public function handle(Request $request)
    {
        $action = $request->get('action');
        switch($action) {
            case 'index':
                $result = $this->handleIndexAction($request);
                break;
            case 'show':
                $result =  $this->handleShowAction($request);
                break;
            case 'new':
                $result = $this->handleNewAction($request);
                break;
            case 'edit':
                $result = $this->handleEditAction($request);
                break;
            case 'delete':
                $result = $this->handleDeleteAction($request);
                break;
            default:
                $message  = "The action '" . $action;
                $message .= "' is not one of the allowed CRUD actions ('index', 'show', 'new', 'edit', 'delete').";
                throw new \LogicException($message);
        }

        return $result;
    }

    /**
     * This method handles index action requests.
     *
     * @param  Request $request
     *
     * @return View
     */
    public function handleIndexAction(Request $request)
    {
        $em = $this->registry->getManager();
        $entityClass = $request->get('entity_class');
        $repository = $em->getRepository($entityClass);

        $event = new IndexActionEvent($request, $repository);
        $this->dispatcher->dispatch(CrudControllerEvents::INDEX_INITIALIZE, $event);

        if (null !== $event->getView()) {
            return $event->getView();
        }

        if (null === $entities = $event->getEntities()) {
            $entities = $repository->findAll();
        }

        $event = new IndexActionEvent($request, $repository, $entities);
        $this->dispatcher->dispatch(CrudControllerEvents::INDEX_FETCHED, $event);

        if (null === $view = $event->getView()) {
            $view = View::create(array('entities' => $entities));
        }

        $event = new IndexActionEvent($request, $repository, $entities, $view);
        $this->dispatcher->dispatch(CrudControllerEvents::INDEX_COMPLETED, $event);

        return $event->getView();
    }

    /**
     * This method handles show action requests.
     *
     * @param Request $request
     *
     * @return View
     */
    public function handleShowAction(Request $request)
    {
        $entity = $request->get('entity');
        $event = new ShowActionEvent($request, $entity);
        $this->dispatcher->dispatch(CrudControllerEvents::SHOW_INITIALIZE, $event);

        if (null === $view = $event->getView()) {
            $view = View::create(array('entity' => $entity));
        }

        $event = new ShowActionEvent($request, $entity, $view);
        $this->dispatcher->dispatch(CrudControllerEvents::SHOW_COMPLETED, $event);

        return $event->getView();
    }

    /**
     * This method handles new action requests.
     *
     * @param Request $request
     * @return View
     */
    public function handleNewAction(Request $request)
    {
        $registry = $this->registry;
        $entityClass = $request->get('entity_class');
        $entity = new $entityClass();
        $form = $this->createEntityForm($request, $entity);

        $events = array(
            'class' => NewActionEvent::class,
            'initialize' => CrudControllerEvents::NEW_INITIALIZE,
            'submitted' => CrudControllerEvents::NEW_SUBMITTED,
            'success' => CrudControllerEvents::NEW_SUCCESS,
            'completed' => CrudControllerEvents::NEW_COMPLETED
        );

        $handler = function(Request $request, EntityEvent $event) use ($registry) {
            $entity = $event->getEntity();
            $em = $registry->getManagerForClass(get_class($entity));
            $em->persist($entity);
            $em->flush();

            return $entity;
        };

        return $this->handleFormRequest($request, $entity, $form, $events, $handler);
    }

    /**
     * This method handles edit action requests.
     *
     * @param Request $request
     *
     * @return View
     */
    public function handleEditAction(Request $request)
    {
        $registry = $this->registry;
        $entity = $request->get('entity');
        $form = $this->createEntityForm($request, $entity, array('method' => 'PUT'));

        $events = array(
            'class' => EditActionEvent::class,
            'initialize' => CrudControllerEvents::EDIT_INITIALIZE,
            'submitted' => CrudControllerEvents::EDIT_SUBMITTED,
            'success' => CrudControllerEvents::EDIT_SUCCESS,
            'completed' => CrudControllerEvents::EDIT_COMPLETED
        );

        $handler = function(Request $request, EntityEvent $event) use ($registry) {
            $entity = $event->getEntity();
            $em = $registry->getManagerForClass(get_class($entity));
            $em->persist($entity);
            $em->flush();

            return $entity;
        };

        return $this->handleFormRequest($request, $entity, $form, $events, $handler);
    }

    /**
     * This method handles delete action requests.
     *
     * @param Request $request
     *
     * @return View
     */
    public function handleDeleteAction(Request $request)
    {
        $registry = $this->registry;
        $entity = $request->get('entity');
        $form = $this->createDeleteForm();

        $events = array(
            'class' => DeleteActionEvent::class,
            'initialize' => CrudControllerEvents::DELETE_INITIALIZE,
            'submitted' => CrudControllerEvents::DELETE_SUBMITTED,
            'success' => CrudControllerEvents::DELETE_SUCCESS,
            'completed' => CrudControllerEvents::DELETE_COMPLETED
        );

        $handler = function(Request $request, EntityEvent $event) use ($registry) {
            $entity = $event->getEntity();
            $em = $registry->getManagerForClass(get_class($entity));
            $em->remove($entity);
            $em->flush();

            return null;
        };

        return $this->handleFormRequest($request, $entity, $form, $events, $handler);
    }

    /**
     * Creates a form to delete an entity.
     *
     * @return FormInterface
     */
    private function createDeleteForm()
    {
        return $this->factory->createBuilder()
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Creates a form to edit an entity.
     *
     * @param  Request $request
     * @param  Entity  $entity
     * @param  array   $options
     * @return FormInterface
     */
    private function createEntityForm(Request $request, Entity $entity, $options = array())
    {
        $type = $request->get('form_type');
        if (null === $type) {
            $type = str_replace("\\Entity\\", "\\Form\\", $request->get('entity_class')) . "Type";
        }

        if (! class_exists($type)) {
            $message  = "Cannot find form ";
            $message .= $type;
            $message .= ". Please specify the form FQCN using form_type request attribute.";
            throw new \LogicException($message);
        }

        return $this->factory->create($type, $entity, $options);
    }

    /**
     * @param  Request $request
     * @param  mixed   $entity
     * @return string
     */
    protected function getRedirect(Request $request, $entity)
    {
        $route = $request->get('redirect');
        $parameters = array();
        if (null === $route) {
            switch($request->get('action')) {
                case 'new':
                case 'edit':
                    $route = $this->getPrefix($request) . 'show';
                    $parameters = array('id' => $entity->getId());
                    break;
                case 'delete':
                    $route = $this->getPrefix($request) . 'index';
                    break;
            }
        }

        return array('route' => $route, 'parameters' => $parameters);
    }
}
