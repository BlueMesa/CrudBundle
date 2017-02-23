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

use Bluemesa\Bundle\AclBundle\Doctrine\OwnedObjectManager;
use Bluemesa\Bundle\CrudBundle\Event\ShowActionEvent;
use Bluemesa\Bundle\CoreBundle\Doctrine\ObjectManagerRegistry;
use JMS\DiExtraBundle\Annotation as DI;


/**
 * The CrudAnnotationListener handles Pagination annotation for controllers.
 *
 * @DI\Service("bluemesa.crud.listener.owned_entity")
 * @DI\Tag("kernel.event_listener",
 *     attributes = {
 *         "event" = "bluemesa.controller.show_completed",
 *         "method" = "onShowCompleted",
 *         "priority" = 100
 *     }
 * )
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class CrudOwnedEntityListener
{
    /**
     * @var ObjectManagerRegistry
     */
    protected $registry;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "registry" = @DI\Inject("bluemesa.core.doctrine.registry")
     * })
     *
     * @param ObjectManagerRegistry $registry
     */
    public function __construct(ObjectManagerRegistry $registry)
    {
        $this->registry = $registry;
    }


    /**
     * @param ShowActionEvent $event
     */
    public function onShowCompleted(ShowActionEvent $event)
    {
        $entity = $event->getEntity();
        $view = $event->getView();
        $om = $this->registry->getManagerForClass($entity);
        if ($om instanceof OwnedObjectManager) {
            $owner = $om->getOwner($entity);
            $view->setTemplateData(array_merge($view->getTemplateData(), array('owner' => $owner)));
        }
    }
}
