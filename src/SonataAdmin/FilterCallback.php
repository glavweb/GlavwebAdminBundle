<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use Doctrine\ORM\QueryBuilder;
use Sonata\AdminBundle\Filter\Model\FilterData;

/**
 * Sonata 4 CallbackFilter helpers + регистронезависимый поиск.
 */
final class FilterCallback
{
    public static function value(FilterData $data): mixed
    {
        if (!$data->hasValue()) {
            return null;
        }

        $value = $data->getValue();

        if (null === $value || '' === $value || [] === $value) {
            return null;
        }

        return $value;
    }

    /**
     * LIKE по нескольким DQL-выражениям без учёта регистра.
     *
     * @param list<string> $expressions поля/выражения, к которым применяется LOWER(... ) LIKE
     */
    public static function applyCaseInsensitiveContains(
        QueryBuilder $qb,
        array $expressions,
        FilterData $data,
        string $parameter = 'ci_search',
    ): bool {
        $value = self::value($data);
        if (null === $value) {
            return false;
        }

        if ([] === $expressions) {
            return false;
        }

        $ors = [];
        foreach ($expressions as $expression) {
            $ors[] = "LOWER($expression) LIKE :$parameter";
        }

        $qb->andWhere(implode(' OR ', $ors))
            ->setParameter($parameter, '%'.mb_strtolower((string) $value, 'UTF-8').'%');

        return true;
    }

    /**
     * Поиск по firstname / lastname и полному имени в обоих порядках.
     */
    public static function applyCaseInsensitiveFullName(
        QueryBuilder $qb,
        string $alias,
        FilterData $data,
        string $parameter = 'ci_full_name',
    ): bool {
        return self::applyCaseInsensitiveContains($qb, [
            "$alias.firstname",
            "$alias.lastname",
            "CONCAT($alias.lastname, ' ', $alias.firstname)",
            "CONCAT($alias.firstname, ' ', $alias.lastname)",
        ], $data, $parameter);
    }
}
