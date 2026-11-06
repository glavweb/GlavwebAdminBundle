<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use InvalidArgumentException;
use Sonata\AdminBundle\Admin\AdminInterface;

/**
 * Контекст расширенного фильтра: Admin + произвольные attributes (company, …).
 */
final class AdvancedFilterContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly AdminInterface $admin,
        public readonly array $attributes = [],
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function require(string $key): mixed
    {
        if (!\array_key_exists($key, $this->attributes)) {
            throw new InvalidArgumentException(sprintf('Advanced filter context attribute "%s" is required.', $key));
        }

        return $this->attributes[$key];
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->attributes);
    }
}
