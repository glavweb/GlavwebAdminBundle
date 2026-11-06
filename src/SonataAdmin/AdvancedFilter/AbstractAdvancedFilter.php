<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use DateTimeInterface;
use DateTime;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;

/**
 * Базовые хелперы для расширенных фильтров.
 */
abstract class AbstractAdvancedFilter implements AdvancedFilterInterface
{
    public function isActive(mixed $value): bool
    {
        if (null === $value || false === $value || '' === $value) {
            return false;
        }

        if (\is_array($value)) {
            return [] !== array_filter($value, static fn ($item): bool => null !== $item && '' !== $item && false !== $item);
        }

        return true;
    }

    public function normalizeFormValue(mixed $value): mixed
    {
        return $value;
    }

    public function toFormData(mixed $filterValue): mixed
    {
        return $filterValue;
    }

    /**
     * @param int[]|string[] $ids
     */
    protected function applyPrimaryIds(
        ProxyQuery $query,
        string $alias,
        array $ids,
        string $parameterName,
    ): bool {
        $qb = $query->getQueryBuilder();

        if ([] === $ids) {
            $qb->andWhere('1 = 0');

            return true;
        }

        $qb->andWhere("$alias.id IN (:$parameterName)")
            ->setParameter($parameterName, $ids);

        return true;
    }

    protected function parseDate(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (\is_string($value) && '' !== $value) {
            $date = DateTime::createFromFormat('Y-m-d', $value)
                ?: DateTime::createFromFormat('d.m.Y', $value);

            return false !== $date ? $date : null;
        }

        return null;
    }

    /**
     * @return array{0: DateTimeInterface, 1: DateTimeInterface}|null
     */
    protected function parseDateRange(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $start = $this->parseDate($value['start'] ?? null);
        $end = $this->parseDate($value['end'] ?? null);

        if (null === $start || null === $end) {
            return null;
        }

        return [$start, $end];
    }
}
