<?php

namespace Glavweb\AdminBundle\Security\Handler;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Security\Handler\RoleSecurityHandler as BaseRoleSecurityHandler;
use Glavweb\AdminBundle\Admin\Admin as GlavwebAdmin;

/**
 * Class RoleSecurityHandler
 * @package Glavweb\AdminBundle\Security\Handler
 */
class RoleSecurityHandler extends BaseRoleSecurityHandler
{
    /**
     * {@inheritDoc}
     */
    public function isGranted(AdminInterface $admin, $attributes, $object = null)
    {
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }

        $surrogateRoles = array();
        if ($admin instanceof GlavwebAdmin) {
            $surrogateRoles = $admin->surrogateRoles();
        }

        $preparedAttributes = array();
        foreach ($attributes as $attribute) {
            if (isset($surrogateRoles[$attribute])) {
                foreach ($surrogateRoles[$attribute] as $surrogateAttribute) {
                    $preparedAttributes[] = $this->addBaseRole($admin, $surrogateAttribute);
                }
            } else {
                $preparedAttributes[] = $this->addBaseRole($admin, $attribute);
            }
        }

        try {
            return $this->securityContext->isGranted($this->superAdminRoles)
            || $this->securityContext->isGranted($preparedAttributes, $object);
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseRole(AdminInterface $admin)
    {
        if ($admin instanceof GlavwebAdmin) {
            return $admin->getBaseRole();
        }

        return parent::getBaseRole($admin);
    }

    /**
     * @param AdminInterface $admin
     * @param string         $attribute
     * @return string
     */
    protected function addBaseRole(AdminInterface $admin, $attribute)
    {
        if (strpos($attribute, 'ROLE_') === 0) {
            return $attribute;
        }

        return sprintf($this->getBaseRole($admin), $attribute);
    }
}
