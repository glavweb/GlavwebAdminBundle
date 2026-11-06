<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

/**
 * Описание одного поля advanced-страницы (AdvancedFilterInterface или обычный Sonata filter).
 */
final class AdvancedFilterField
{
    /**
     * @param class-string<AdvancedFilterInterface>|null $advancedFilterClass
     * @param array<string, mixed>                       $filterOptions
     * @param array<string, mixed>                       $fieldDescriptionOptions
     * @param array<string, mixed>                       $groupOptions
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly array $filterOptions,
        public readonly array $fieldDescriptionOptions,
        public readonly string $group,
        public readonly array $groupOptions,
        public readonly ?string $advancedFilterClass,
    ) {
    }

    public function isAdvancedFilter(): bool
    {
        return null !== $this->advancedFilterClass;
    }
}
