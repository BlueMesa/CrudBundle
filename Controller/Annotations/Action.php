<?php

/*
 * This file is part of the CRUD Bundle.
 * 
 * Copyright (c) 2016 BlueMesa LabDB Contributors <labdb@bluemesa.eu>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Bluemesa\Bundle\CrudBundle\Controller\Annotations;

use Bluemesa\Bundle\CoreBundle\Controller\Annotations\Action as BaseAction;

/**
 * Action Annotation
 *
 * @Annotation
 * @Target("METHOD")
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
class Action extends BaseAction
{
    /**
     * @var string
     */
    private $form;


    /**
     * Action Annotation constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        parent::__construct($values);
        $this->form = array_key_exists('form', $values) ? $values['form'] : null;
    }

    /**
     * @return string
     */
    public function getFormType()
    {
        return $this->form;
    }
}
