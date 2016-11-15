<?php

/*
 * This file is part of the BluemesaCoreBundle.
 * 
 * Copyright (c) 2016 BlueMesa LabDB Contributors <labdb@bluemesa.eu>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bluemesa\Bundle\CrudBundle\Filter;

use Bluemesa\Bundle\CoreBundle\Filter\RedirectFilterInterface as BaseRedirectFilterInterface;

/**
 * This interface adds support for automatic URL rewrite redirects when filter is applied
 *
 * @author Radoslaw Kamil Ejsmont <radoslaw@ejsmont.net>
 */
interface RedirectFilterInterface extends BaseRedirectFilterInterface {

    /**
     * Is redirect needed?
     *
     * @return boolean
     */
    public function needRedirect();

    /**
     * Return filter parameters
     *
     * @return array
     */
    public function getParameters();
}
