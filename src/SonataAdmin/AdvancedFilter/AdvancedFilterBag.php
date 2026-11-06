<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

/**
 * Собранная конфигурация advanced-фильтров Admin'а.
 */
final class AdvancedFilterBag
{
    /**
     * @param list<AdvancedFilterField>           $fields
     * @param array<string, array<string, mixed>> $groups group name => options (order preserved)
     */
    public function __construct(
        private readonly array $fields,
        private readonly array $groups,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->fields;
    }

    /**
     * @return list<AdvancedFilterField>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return list<AdvancedFilterField>
     */
    public function getFieldsInGroup(string $group): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (AdvancedFilterField $field): bool => $field->group === $group
        ));
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_map(static fn (AdvancedFilterField $field): string => $field->name, $this->fields);
    }

    /**
     * @return list<string>
     */
    public function getAdvancedFilterNames(): array
    {
        return array_values(array_map(
            static fn (AdvancedFilterField $field): string => $field->name,
            array_filter($this->fields, static fn (AdvancedFilterField $field): bool => $field->isAdvancedFilter())
        ));
    }

    public function getField(string $name): ?AdvancedFilterField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }
}
