<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Резолв расширенного фильтра по FQCN (сервисы с интерфейсом AdvancedFilterInterface).
 */
final class AdvancedFilterLocator
{
    public function __construct(
        #[AutowireLocator('glavweb.advanced_filter')]
        private readonly ContainerInterface $locator,
    ) {
    }

    /**
     * @param class-string<AdvancedFilterInterface> $class
     */
    public function get(string $class): AdvancedFilterInterface
    {
        if (!$this->locator->has($class)) {
            throw new InvalidArgumentException(sprintf('Advanced filter "%s" is not registered as a service.', $class));
        }

        $filter = $this->locator->get($class);
        if (!$filter instanceof AdvancedFilterInterface) {
            throw new InvalidArgumentException(sprintf('Service "%s" must implement AdvancedFilterInterface.', $class));
        }

        return $filter;
    }
}
