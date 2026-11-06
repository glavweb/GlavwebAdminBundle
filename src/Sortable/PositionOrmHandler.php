<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Sortable;

use ReflectionClass;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class PositionOrmHandler.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class PositionOrmHandler extends PositionHandler
{
    private static array $cacheLastPosition = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(): void
    {
        $this->entityManager->flush();
    }

    public function getLastPosition(object $entity): int
    {
        $entityClass = self::getRealClass($entity);
        $parentEntityClass = true;

        while ($parentEntityClass) {
            $parentEntityClass = get_parent_class($entityClass);
            if ($parentEntityClass) {
                $reflection = new ReflectionClass($parentEntityClass);
                if ($reflection->isAbstract()) {
                    break;
                }
                $entityClass = $parentEntityClass;
            }
        }

        $groups = $this->getSortableGroupsFieldByEntity($entityClass);
        $cacheKey = $this->getCacheKeyForLastPosition($entity, $groups);

        if (!isset(self::$cacheLastPosition[$cacheKey])) {
            $qb = $this->entityManager->createQueryBuilder()
                ->select(sprintf('MAX(t.%s) as last_position', $this->getPositionFieldByEntity($entityClass)))
                ->from($entityClass, 't');

            if ($groups) {
                $i = 1;
                foreach ($groups as $groupName) {
                    $getter = 'get'.$groupName;
                    if ($entity->$getter()) {
                        $qb
                            ->andWhere(sprintf('t.%s = :group_%s', $groupName, $i))
                            ->setParameter(sprintf('group_%s', $i), $entity->$getter());
                        ++$i;
                    }
                }
            }

            self::$cacheLastPosition[$cacheKey] = (int) $qb->getQuery()->getSingleScalarResult();
        }

        return self::$cacheLastPosition[$cacheKey];
    }

    private function getCacheKeyForLastPosition(object $entity, array $groups): string
    {
        $cacheKey = self::getRealClass($entity);

        foreach ($groups as $groupName) {
            $getter = 'get'.$groupName;
            $groupValue = $entity->$getter();
            if ($groupValue) {
                $cacheKey .= '_'.(is_object($groupValue) && method_exists($groupValue, 'getId')
                    ? $groupValue->getId()
                    : (string) $groupValue);
            }
        }

        return $cacheKey;
    }
}
