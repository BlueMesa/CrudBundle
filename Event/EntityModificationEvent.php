<?php

/*
 * This file is part of the XXX.
 * 
 * Copyright (c) 2016 BlueMesa LabDB Contributors <labdb@bluemesa.eu>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Bluemesa\Bundle\CrudBundle\Event;


use Bluemesa\Bundle\CoreBundle\Event\FormEventInterface;
use Bluemesa\Bundle\CoreBundle\Event\FormEventTrait;
use Symfony\Component\Form\FormInterface;

class EntityModificationEvent extends EntityEvent implements FormEventInterface
{
    use FormEventTrait;
}
