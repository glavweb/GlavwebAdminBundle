<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\Filter;

use Sonata\AdminBundle\Filter\FilterFactoryInterface;
use Sonata\AdminBundle\Filter\FilterInterface;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;

/**
 * Строковые фильтры Sonata 4 по умолчанию регистрозависимы; опция
 * `case_sensitive` переименована в `force_case_insensitivity` (с инверсией смысла).
 * Восстанавливает прежнее поведение: text-фильтры регистронезависимы.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
final class CaseInsensitiveStringFilterFactory implements FilterFactoryInterface
{
    public function __construct(
        private readonly FilterFactoryInterface $inner,
    ) {
    }

    public function create(string $name, string $type, array $options = []): FilterInterface
    {
        if ($this->isStringFilterType($type)) {
            if (\array_key_exists('case_sensitive', $options)) {
                $options['force_case_insensitivity'] = !(bool) $options['case_sensitive'];
                unset($options['case_sensitive']);
            } elseif (!\array_key_exists('force_case_insensitivity', $options)) {
                $options['force_case_insensitivity'] = true;
            }
        }

        return $this->inner->create($name, $type, $options);
    }

    private function isStringFilterType(string $type): bool
    {
        return StringFilter::class === $type
            || 'doctrine_orm_string' === $type
            || 'sonata.admin.orm.filter.type.string' === $type;
    }
}
