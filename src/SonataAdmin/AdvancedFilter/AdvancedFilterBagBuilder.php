<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Translator\LabelTranslatorStrategyInterface;

/**
 * Builds and caches the AdvancedFilterBag of one admin, resolves active filter labels.
 *
 * Owned by AbstractAdmin; admins keep configuring filters via
 * AbstractAdmin::configureAdvancedDatagridFilters().
 *
 * @author nilov <nilov@glavweb.ru>
 */
final class AdvancedFilterBagBuilder
{
    private ?AdvancedFilterBag $bag = null;

    /**
     * @param \Closure(AdvancedDatagridMapper): void $configure
     */
    public function __construct(private readonly \Closure $configure)
    {
    }

    public function getBag(): AdvancedFilterBag
    {
        if (null === $this->bag) {
            $mapper = new AdvancedDatagridMapper();
            ($this->configure)($mapper);
            $this->bag = $mapper->buildBag();
        }

        return $this->bag;
    }

    public function reset(): void
    {
        $this->bag = null;
    }

    public function hasFilters(): bool
    {
        return !$this->getBag()->isEmpty();
    }

    /**
     * Labels of advanced filters that currently have a value.
     *
     * @param array<string, mixed>                                         $datagridValues
     * @param \Closure(string $id, array<string, mixed> $params, string $domain): string $translate
     *
     * @return list<string>
     */
    public function getActiveLabels(
        array $datagridValues,
        LabelTranslatorStrategyInterface $labelStrategy,
        string $defaultDomain,
        \Closure $translate,
    ): array {
        if (!$this->hasFilters()) {
            return [];
        }

        $labels = [];
        foreach ($this->getBag()->getFields() as $field) {
            $value = $datagridValues[$field->name]['value'] ?? null;
            if (null === $value || '' === $value || [] === $value) {
                continue;
            }

            $label = $field->filterOptions['label'] ?? null;
            if (null === $label || false === $label) {
                $label = $labelStrategy->getLabel($field->name, 'filter', 'label');
            }

            if (\is_string($label)) {
                $domain = $field->filterOptions['translation_domain'] ?? $defaultDomain;
                $labels[] = false === $domain ? $label : $translate($label, [], $domain);
            }
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $filterParameters
     *
     * @return array<string, mixed>
     */
    public function stripAdvancedParameters(array $filterParameters): array
    {
        if (!$this->hasFilters()) {
            return $filterParameters;
        }

        foreach ($this->getBag()->getNames() as $name) {
            unset($filterParameters[$name]);
        }

        return $filterParameters;
    }
}
