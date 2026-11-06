<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\Filter;

use TypeError;
use RuntimeException;
use UnexpectedValueException;
use Glavweb\AdminBundle\SonataAdmin\NativeQueryBuilder;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Filter\Filter;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Callback-фильтр для native SQL datagrid (Sonata ORM-фильтры требуют ProxyQuery ORM).
 */
final class NativeCallbackFilter extends Filter
{
    public function apply(ProxyQueryInterface $query, FilterData $filterData): void
    {
        if (!$query instanceof NativeQueryBuilder) {
            throw new TypeError(sprintf('The query MUST implement %s.', NativeQueryBuilder::class));
        }

        if (!$filterData->hasValue()) {
            return;
        }

        if (!\is_callable($this->getOption('callback'))) {
            throw new RuntimeException(sprintf('Please provide a valid callback option for field "%s".', $this->getName()));
        }

        $alias = (string) $this->getOption('alias', 's');
        $field = $this->getOption('field_name', $this->getName());

        $isActive = \call_user_func($this->getOption('callback'), $query, $alias, $field, $filterData);
        if (!\is_bool($isActive)) {
            throw new UnexpectedValueException(sprintf('The callback should return a boolean, %s returned.', \is_object($isActive) ? 'instance of "'.$isActive::class.'"' : '"'.\gettype($isActive).'"'));
        }

        $this->setActive($isActive);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultOptions(): array
    {
        return [
            'callback' => null,
            'alias' => 's',
            'field_name' => null,
            'field_type' => TextType::class,
            'operator_type' => HiddenType::class,
            'operator_options' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormOptions(): array
    {
        return [
            'field_type' => $this->getFieldType(),
            'field_options' => $this->getFieldOptions(),
            'operator_type' => $this->getOption('operator_type'),
            'operator_options' => $this->getOption('operator_options'),
            'label' => $this->getLabel(),
        ];
    }
}
