<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

/**
 * Конфигурация расширенных фильтров (как DatagridMapper + with/end как FormMapper).
 *
 * @example
 * $mapper
 *     ->with('group.crm', ['label' => 'CRM', 'class' => 'col-md-12'])
 *         ->add('adv_form_response_category', FormResponseCategoryAdvancedFilter::class)
 *         ->add('fullName', CallbackFilter::class, ['callback' => ...])
 *         ->add('email')
 *     ->end();
 */
final class AdvancedDatagridMapper
{
    private const string DEFAULT_GROUP = 'default';

    /**
     * @var list<AdvancedFilterField>
     */
    private array $fields = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $groups = [];

    private string $currentGroup = self::DEFAULT_GROUP;

    /**
     * @param array<string, mixed> $options
     */
    public function with(string $name, array $options = []): self
    {
        if (!isset($this->groups[$name])) {
            $this->groups[$name] = $options;
        } else {
            $this->groups[$name] = array_replace($this->groups[$name], $options);
        }

        $this->currentGroup = $name;

        return $this;
    }

    public function end(): self
    {
        $this->currentGroup = self::DEFAULT_GROUP;

        return $this;
    }

    /**
     * @param class-string|null    $type                    AdvancedFilterInterface FQCN, Sonata filter FQCN, or null (default string filter)
     * @param array<string, mixed> $filterOptions
     * @param array<string, mixed> $fieldDescriptionOptions
     */
    public function add(
        string $name,
        ?string $type = null,
        array $filterOptions = [],
        array $fieldDescriptionOptions = [],
    ): self {
        if (self::DEFAULT_GROUP === $this->currentGroup && !isset($this->groups[self::DEFAULT_GROUP])) {
            $this->groups[self::DEFAULT_GROUP] = ['label' => false];
        }

        $advancedFilterClass = null;
        if (null !== $type && is_a($type, AdvancedFilterInterface::class, true)) {
            $advancedFilterClass = $type;
            $type = null;
        }

        $this->fields[] = new AdvancedFilterField(
            $name,
            $type,
            $filterOptions,
            $fieldDescriptionOptions,
            $this->currentGroup,
            $this->groups[$this->currentGroup] ?? [],
            $advancedFilterClass,
        );

        return $this;
    }

    public function has(string $name): bool
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function buildBag(): AdvancedFilterBag
    {
        return new AdvancedFilterBag($this->fields, $this->groups);
    }
}
