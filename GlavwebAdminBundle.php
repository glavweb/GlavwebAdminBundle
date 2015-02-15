<?php

namespace Glavweb\AdminBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class GlavwebAdminBundle extends Bundle
{
    /**
     * Returns the bundle parent name.
     *
     * @return string The Bundle parent name it overrides or null if no parent
     *
     * @api
     */
    public function getParent()
    {
        return 'SonataAdminBundle';
    }
}
