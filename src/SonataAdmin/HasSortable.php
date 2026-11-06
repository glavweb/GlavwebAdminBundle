<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use Sonata\AdminBundle\Admin\AdminInterface;

/**
 * Marker: list is sorted by `position` and exposes the `move` route.
 */
interface HasSortable extends AdminInterface
{
}
