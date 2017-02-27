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

use Bluemesa\Bundle\CoreBundle\Entity\MutableIdEntityInterface;
use Bluemesa\Bundle\CrudBundle\Event\CrudControllerEvents;
use Bluemesa\Bundle\CrudBundle\Event\EntityModificationEvent;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * The CrudAnnotationListener handles Pagination annotation for controllers.
 *
 * @DI\Service("bluemesa.crud.listener.mutable_id")
 * @DI\Tag("kernel.event_subscriber")
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class CrudMutableIdListener implements EventSubscriberInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $doctrine;


    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "doctrine" = @DI\Inject("doctrine")
     * })
     *
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            CrudControllerEvents::NEW_SUBMITTED => array('setMutableId', 1000),
            CrudControllerEvents::EDIT_SUBMITTED => array('setMutableId', 1000)
        );
    }

    /**
     * @param EntityModificationEvent $event
     */
    public function setMutableId(EntityModificationEvent $event)
    {
        $request = $event->getRequest();
        $entity = $event->getEntity();

        // Check if ID of the entity has been specified or modified
        if ((! $entity instanceof MutableIdEntityInterface)||($entity->getId() == null)||
            ($request->get('id') == $entity->getId())) {

            return;
        }

        // Temporarily disable ID generator
        $em = $this->doctrine->getManagerForClass(get_class($entity));
        if ($em instanceof EntityManagerInterface) {
            $metadata = $em->getClassMetadata(get_class($entity));
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
            $metadata->setIdGenerator(new AssignedGenerator());
        }
    }
}
