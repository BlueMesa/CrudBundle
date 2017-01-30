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

use Bluemesa\Bundle\CoreBundle\EventListener\PaginationListener;
use Bluemesa\Bundle\CoreBundle\Repository\EntityRepositoryInterface;
use Bluemesa\Bundle\CrudBundle\Event\IndexActionEvent;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\EventDispatcher\Event;


/**
 * The CrudAnnotationListener handles Pagination annotation for controllers.
 *
 * @DI\Service("bluemesa.crud.listener.pagination")
 * @DI\Tag("kernel.event_listener",
 *     attributes = {
 *         "event" = "bluemesa.controller.index_initialize",
 *         "method" = "onPaginate",
 *         "priority" = 100
 *     }
 * )
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class CrudPaginationListener extends PaginationListener
{
    /**
     * @param  Event  $event
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getPaginationTarget(Event $event)
    {
        if (! $event instanceof IndexActionEvent) {
            throw new \InvalidArgumentException("The event " . get_class($event) .
                " must be an instance of IndexActionEvent.");
        }

        $repository = $event->getRepository();

        if ($repository instanceof EntityRepositoryInterface) {
            $count = $repository->getIndexCount();
            $query = $repository->createIndexQuery()->setHint('knp_paginator.count', $count);
        } else {
            $query = $repository->findAll();
        }

        return $query;
    }

    /**
     * @param  Event $event
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getPaginationOptions(Event $event)
    {
        if (! $event instanceof IndexActionEvent) {
            throw new \InvalidArgumentException("The event " . get_class($event) .
                " must be an instance of IndexActionEvent.");
        }

        return $event->getRepository() instanceof EntityRepositoryInterface ?
            parent::getPaginationOptions($event) : array();
    }
}
