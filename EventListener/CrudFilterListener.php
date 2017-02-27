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

use Bluemesa\Bundle\AclBundle\Filter\SecureFilterInterface;
use Bluemesa\Bundle\CoreBundle\Repository\FilteredRepositoryInterface;
use Bluemesa\Bundle\CrudBundle\Controller\Annotations\Filter;
use Bluemesa\Bundle\CrudBundle\Event\CrudControllerEvents;
use Bluemesa\Bundle\CrudBundle\Event\IndexActionEvent;
use Bluemesa\Bundle\CrudBundle\Filter\RedirectFilterInterface;
use Doctrine\Common\Annotations\Reader;
use FOS\RestBundle\View\View;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;


/**
 * The CrudFilterListener handles Filter annotation for controllers.
 *
 * @DI\Service("bluemesa.crud.listener.filter")
 * @DI\Tag("kernel.event_subscriber")
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class CrudFilterListener implements EventSubscriberInterface
{
    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;


    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "reader" = @DI\Inject("annotation_reader"),
     *     "authorizationChecker" = @DI\Inject("security.authorization_checker"),
     *     "tokenStorage" = @DI\Inject("security.token_storage")
     * })
     *
     * @param Reader $reader
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(Reader $reader, AuthorizationCheckerInterface $authorizationChecker,
                                TokenStorageInterface $tokenStorage)
    {
        $this->reader = $reader;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            CrudControllerEvents::INDEX_INITIALIZE => array('onIndexInitialize', 900),
            CrudControllerEvents::INDEX_COMPLETED => array('onIndexCompleted', 100)
        );
    }

    /**
     * @param IndexActionEvent $event
     */
    public function onIndexInitialize(IndexActionEvent $event)
    {
        $request = $event->getRequest();
        $controller = $this->getController($request);

        if (is_array($controller)) {
            $m = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && is_callable($controller, '__invoke')) {
            $m = new \ReflectionMethod($controller, '__invoke');
        } else {
            return;
        }

        /** @var Filter $filterAnnotation */
        $filterAnnotation = $this->reader->getMethodAnnotation($m, Filter::class);
        if (! $filterAnnotation) {
            return;
        }
        $filterClass = $filterAnnotation->getName();
        $redirectRoute = $filterAnnotation->getRedirectRoute();

        $repository = $event->getRepository();
        if ($repository instanceof FilteredRepositoryInterface) {
            if ($filterClass instanceof SecureFilterInterface) {
                $filter = new $filterClass($request, $this->authorizationChecker, $this->tokenStorage);
            } else {
                $filter = new $filterClass($request);
            }

            /* Redirect to route that handles submitted filter parameters if needed */
            if (($filter instanceof RedirectFilterInterface)&&(!empty($redirectRoute))&&($filter->needRedirect())) {
                $event->setView(
                    View::createRouteRedirect($redirectRoute, $filter->getParameters(), Response::HTTP_FOUND));

                return;
            }

            $repository->setFilter($filter);
            $this->addRequestAttribute($request, 'filter', $filter);
        }
    }

    public function onIndexCompleted(IndexActionEvent $event)
    {
        $request = $event->getRequest();
        $filter = $request->get('filter');
        $view = $event->getView();

        if ((null !== $filter)&&(null !== $view)) {
            $filterData = array('filter' => $filter);
            $templateData = $view->getTemplateData();
            $data = (null !== $templateData) ?
                array_merge($templateData, $filterData) : $filterData;
            $view->setTemplateData($data);
        }
    }

    /**
     * @param  Request  $request
     * @return array
     */
    private function getController($request)
    {
        return explode("::", $request->get('_controller'));
    }

    /**
     * @param Request $request
     * @param string  $attribute
     * @param string  $value
     */
    private function addRequestAttribute(Request $request, $attribute, $value)
    {
        if (! $request->attributes->has($attribute)) {
            $request->attributes->set($attribute, $value);
        }
    }
}
