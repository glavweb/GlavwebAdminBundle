<?php

namespace Glavweb\AdminBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Class EntityAccessInterface
 * @package Glavweb\AdminBundle\Security
 */
interface EntityAccessInterface
{
    /**
     * Additional access
     *
     * @param TokenInterface $token
     * @param array          $attributes
     * @return boolean
     */
    public function access(TokenInterface $token, array $attributes);
}