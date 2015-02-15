<?php

namespace Glavweb\AdminBundle\Admin;

use Sonata\AdminBundle\Admin\Admin as BaseAdmin;

/**
 * Class Admin
 * @package Glavweb\AdminBundle\Admin
 */
class Admin extends BaseAdmin
{
    /**
     * Surrogate security roles. Replace roles.
     *
     * An example: 'LIST' => array('LIST_ALL', 'LIST_MY', 'LIST_MY_OBJECTS')
     * replace ROLE_LIST on ROLE_LIST_ALL, ROLE_LIST_MY, ROLE_LIST_MY_OBJECTS
     *
     * @return array
     */
    public function surrogateRoles()
    {
        return array();
    }

    /**
     * Returns base of roles
     *
     * @return string
     */
    public function getBaseRole()
    {
        return 'ROLE_' . str_replace('.', '_', strtoupper($this->getCode())) . '_%s';
    }
}