<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Расширенный фильтр. Имя и label задаются в AdvancedDatagridMapper::add().
 */
#[AutoconfigureTag('glavweb.advanced_filter')]
interface AdvancedFilterInterface
{
    public function buildForm(FormBuilderInterface $builder, AdvancedFilterContext $context): void;

    public function isActive(mixed $value): bool;

    /**
     * Form data → Sonata filter[name][value].
     */
    public function normalizeFormValue(mixed $value): mixed;

    /**
     * Sonata filter value → form data.
     */
    public function toFormData(mixed $filterValue): mixed;

    public function apply(ProxyQuery $query, string $alias, mixed $value, AdvancedFilterContext $context): bool;
}
