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
use Bluemesa\Bundle\CrudBundle\Event\CrudControllerEvents;
use Bluemesa\Bundle\CrudBundle\Event\DeleteActionEvent;
use Bluemesa\Bundle\CrudBundle\Event\EditActionEvent;
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
     * This method handles new action requests.
     *
     * @param Request $request
     *
     * @return View
     */
    public function handleNewAction(Request $request)
    {
        $entityClass = $request->get('entity_class');

        /** @var Entity $entity */
        $entity = new $entityClass();
        $form = $this->createEntityForm($request, $entity);

        $event = new NewActionEvent($request, $entity, $form);
        $this->dispatcher->dispatch(CrudControllerEvents::NEW_INITIALIZE, $event);

        if (null !== $event->getView()) {
            return $event->getView();
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = new NewActionEvent($request, $entity, $form);
            $this->dispatcher->dispatch(CrudControllerEvents::NEW_SUBMITTED, $event);

            $em = $this->registry->getManagerForClass(get_class($entity));
            $em->persist($entity);
            $em->flush();

            $event = new NewActionEvent($request, $entity, $form, $event->getView());
            $this->dispatcher->dispatch(CrudControllerEvents::NEW_SUCCESS, $event);

            if (null === $view = $event->getView()) {
                $view = View::createRouteRedirect($this->getRedirectRoute($request), array('id' => $entity->getId()));
            }

        } else {
            $view = View::create(array('entity' => $entity, 'form' => $form->createView()));
        }

        $event = new NewActionEvent($request, $entity, $form, $view);
        $this->dispatcher->dispatch(CrudControllerEvents::NEW_COMPLETED, $event);

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
     * This method handles edit action requests.
     *
     * @param Request $request
     *
     * @return View
     */
    public function handleEditAction(Request $request)
    {
        /** @var Entity $entity */
        $entity = $request->get('entity');
        $form = $this->createEntityForm($request, $entity, array('method' => 'PUT'));
        $event = new EditActionEvent($request, $entity, $form);
        $this->dispatcher->dispatch(CrudControllerEvents::EDIT_INITIALIZE, $event);

        if (null !== $event->getView()) {
            return $event->getView();
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = new EditActionEvent($request, $entity, $form);
            $this->dispatcher->dispatch(CrudControllerEvents::EDIT_SUBMITTED, $event);

            $em = $this->registry->getManagerForClass(get_class($entity));
            $em->persist($entity);
            $em->flush();

            $event = new EditActionEvent($request, $entity, $form, $event->getView());
            $this->dispatcher->dispatch(CrudControllerEvents::EDIT_SUCCESS, $event);

            if (null === $view = $event->getView()) {
                $view = View::createRouteRedirect($this->getRedirectRoute($request), array('id' => $entity->getId()));
            }

        } else {
            $view = View::create(array('entity' => $entity, 'form' => $form->createView()));
        }

        $event = new EditActionEvent($request, $entity, $form, $view);
        $this->dispatcher->dispatch(CrudControllerEvents::EDIT_COMPLETED, $event);

        return $event->getView();
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
        /** @var Entity $entity */
        $entity = $request->get('entity');
        $form = $this->createDeleteForm();

        $event = new DeleteActionEvent($request, $entity, $form);
        $this->dispatcher->dispatch(CrudControllerEvents::DELETE_INITIALIZE, $event);

        if (null !== $event->getView()) {
            return $event->getView();
        }

        $form->handleRequest($request);

        if (($form->isSubmitted() && $form->isValid()) ||
            ($request->isMethod('GET') && ($request->get('delete') == 'confirm'))) {

            $event = new DeleteActionEvent($request, $entity, $form);
            $this->dispatcher->dispatch(CrudControllerEvents::DELETE_SUBMITTED, $event);

            $em = $this->registry->getManagerForClass(get_class($entity));
            $em->remove($entity);
            $em->flush();

            $event = new DeleteActionEvent($request, $entity, $form);
            $this->dispatcher->dispatch(CrudControllerEvents::DELETE_SUCCESS, $event);

            if (null === $view = $event->getView()) {
                $view = View::createRouteRedirect($this->getRedirectRoute($request));
            }

        } else {
            $view = View::create(array('entity' => $entity, 'form' => $form->createView()));
        }

        $event = new DeleteActionEvent($request, $entity, $form, $view);
        $this->dispatcher->dispatch(CrudControllerEvents::DELETE_COMPLETED, $event);

        return $event->getView();
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
     * @return string
     */
    private function getRedirectRoute(Request $request)
    {
        $route = $request->get('redirect');
        if (null === $route) {
            switch($request->get('action')) {
                case 'new':
                case 'edit':
                    $route = $this->getPrefix($request) . 'show';
                    break;
                case 'delete':
                    $route = $this->getPrefix($request) . 'index';
                    break;
            }
        }

        return $route;
    }
}
