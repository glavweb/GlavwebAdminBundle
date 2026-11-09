<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Twig;

use Glavweb\AdminBundle\Sortable\PositionHandler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for sortable list position in Sonata Admin.
 *
 * @author nilov <nilov@glavweb.ru>
 */
final class ObjectPositionExtension extends AbstractExtension
{
    public function __construct(
        private readonly PositionHandler $positionHandler,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('currentObjectPosition', $this->currentPosition(...)),
            new TwigFunction('lastPosition', $this->lastPosition(...)),
        ];
    }

    public function currentPosition(object $entity): int
    {
        return $this->positionHandler->getCurrentPosition($entity);
    }

    public function lastPosition(object $entity): int
    {
        return $this->positionHandler->getLastPosition($entity);
    }
}
