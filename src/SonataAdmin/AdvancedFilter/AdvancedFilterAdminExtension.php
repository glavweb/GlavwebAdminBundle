<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use Glavweb\AdminBundle\SonataAdmin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Регистрирует простые advanced-поля в datagrid; AdvancedFilterInterface применяет в configureQuery
 * по сырым filter-параметрам (HiddenType/FilterData не умеют массивы вроде category/mode).
 */
#[AutoconfigureTag(name: 'sonata.admin.extension', attributes: ['global' => true])]
final class AdvancedFilterAdminExtension extends AbstractAdminExtension
{
    public function __construct(
        private readonly AdvancedFilterLocator $advancedFilterLocator,
    ) {
    }

    public function configureDatagridFilters(DatagridMapper $filter): void
    {
        $admin = $filter->getAdmin();
        if (!$admin instanceof AbstractAdmin || !$admin->hasAdvancedFilters()) {
            return;
        }

        foreach ($admin->getAdvancedFilterBag()->getFields() as $field) {
            if ($filter->has($field->name) || $field->isAdvancedFilter()) {
                continue;
            }

            $options = array_replace(['show_filter' => false], $field->filterOptions);
            /** @var class-string|null $fieldType */
            $fieldType = $field->type;
            $filter->add($field->name, $fieldType, $options, $field->fieldDescriptionOptions);
        }
    }

    public function configureQuery(AdminInterface $admin, ProxyQueryInterface $query): void
    {
        if (!$admin instanceof AbstractAdmin || !$admin->hasAdvancedFilters()) {
            return;
        }

        if (!$query instanceof ProxyQuery) {
            return;
        }

        $qb = $query->getQueryBuilder();
        $alias = $qb->getRootAliases()[0] ?? null;
        if (!\is_string($alias) || '' === $alias) {
            return;
        }

        $params = $admin->getFilterParameters();
        $context = $admin->createAdvancedFilterContext();

        foreach ($admin->getAdvancedFilterBag()->getFields() as $field) {
            if (!$field->isAdvancedFilter()) {
                continue;
            }

            $value = $params[$field->name]['value'] ?? null;
            /** @var class-string<AdvancedFilterInterface> $advancedFilterClass */
            $advancedFilterClass = $field->advancedFilterClass;
            $advancedFilter = $this->advancedFilterLocator->get($advancedFilterClass);
            if (!$advancedFilter->isActive($value)) {
                continue;
            }

            $advancedFilter->apply($query, $alias, $value, $context);
        }
    }
}
