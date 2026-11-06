<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Sortable;

use Doctrine\Persistence\Proxy;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Class PositionHandler.
 *
 * Расчёт новой позиции в списке (бывший pixassociates/sortable-behavior-bundle).
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
abstract class PositionHandler
{
    protected array $positionField = [];

    private array $sortableGroups = [];

    private ?PropertyAccessor $accessor = null;

    abstract public function getLastPosition(object $entity): int;

    /**
     * Flush after the caller changed `position` (Gedmo relocates neighbors onFlush).
     */
    abstract public function persist(): void;

    public function setPositionField(array $positionField): void
    {
        $this->positionField = $positionField;
    }

    public function setSortableGroups(array $sortableGroups): void
    {
        $this->sortableGroups = $sortableGroups;
    }

    public function getPositionFieldByEntity(object|string $entity): string
    {
        $entity = self::getRealClass($entity);

        return $this->positionField['entities'][$entity] ?? $this->positionField['default'];
    }

    public function getSortableGroupsFieldByEntity(object|string $entity): array
    {
        $entity = self::getRealClass($entity);

        return $this->sortableGroups['entities'][$entity] ?? [];
    }

    public function getCurrentPosition(object $entity): int
    {
        return (int) $this->getAccessor()->getValue($entity, $this->getPositionFieldByEntity($entity));
    }

    public function getPosition(object $object, string|int $movePosition, int $lastPosition): int
    {
        $currentPosition = $this->getCurrentPosition($object);
        $newPosition = 0;

        switch ($movePosition) {
            case 'up':
                if ($currentPosition > 0) {
                    $newPosition = $currentPosition - 1;
                }
                break;

            case 'down':
                if ($currentPosition < $lastPosition) {
                    $newPosition = $currentPosition + 1;
                }
                break;

            case 'top':
                if ($currentPosition > 0) {
                    $newPosition = 0;
                }
                break;

            case 'bottom':
                if ($currentPosition < $lastPosition) {
                    $newPosition = $lastPosition;
                }
                break;

            default:
                if (is_numeric($movePosition)) {
                    $newPosition = (int) $movePosition;
                }
        }

        return $newPosition;
    }

    protected static function getRealClass(object|string $entity): string
    {
        $class = \is_object($entity) ? $entity::class : $entity;

        if (is_subclass_of($class, Proxy::class)) {
            $parent = get_parent_class($class);
            if (false !== $parent) {
                $class = $parent;
            }
        }

        return $class;
    }

    private function getAccessor(): PropertyAccessor
    {
        return $this->accessor ??= PropertyAccess::createPropertyAccessor();
    }
}
