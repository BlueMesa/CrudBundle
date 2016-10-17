<?php

/*
 * This file is part of the CRUD Bundle.
 * 
 * Copyright (c) 2016 BlueMesa LabDB Contributors <labdb@bluemesa.eu>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Bluemesa\Bundle\CrudBundle\Event;


use Bluemesa\Bundle\CoreBundle\Entity\EntityInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class EditActionEvent extends EntityModificationEvent
{
    /**
     * EditActionEvent constructor.
     *
     * @param Request $request
     * @param EntityInterface $entity
     * @param Form $form
     * @param View $view
     */
    public function __construct(Request $request, EntityInterface $entity, Form $form, View $view = null)
    {
        $this->request = $request;
        $this->entity = $entity;
        $this->form = $form;
        $this->view = $view;
    }
}
