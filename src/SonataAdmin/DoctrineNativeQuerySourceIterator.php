<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use TypeError;
use Sonata\Exporter\Source\AbstractPropertySourceIterator;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

/**
 * Class DoctrineNativeQuerySourceIterator.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class DoctrineNativeQuerySourceIterator extends AbstractPropertySourceIterator
{
    protected NativeQueryBuilder $query;
    private ?ItemProcessorInterface $itemProcessor;

    /**
     * @param array<string> $fields Fields to export
     */
    public function __construct(NativeQueryBuilder $queryBuilder, array $fields, ?ItemProcessorInterface $itemProcessor = null, string $dateTimeFormat = 'r')
    {
        $this->query = clone $queryBuilder;
        $this->query->setParameters($queryBuilder->getParameters());

        $this->itemProcessor = $itemProcessor;

        parent::__construct($fields, $dateTimeFormat);
    }

    public function current(): array
    {
        $current = (array) $this->iterator?->current() ?? [];

        if ($this->itemProcessor) {
            $this->itemProcessor->processItem($current);
        }

        return $this->getCurrentData($current);
    }

    public function rewind(): void
    {
        $iterator = $this->query->executeQuery()->iterateAssociative();
        $this->iterator = $iterator instanceof \Iterator
            ? $iterator
            : new \ArrayIterator(is_array($iterator) ? $iterator : iterator_to_array($iterator));
        $this->iterator->rewind();
    }

    /**
     * {@inheritDoc}
     */
    protected function getCurrentData(object|array $current): array
    {
        /** @var array<string, mixed> $current */
        $data = [];
        foreach ($this->fields as $key => $field) {
            if (\is_string($field)) {
                $name = \is_string($key) ? $key : $field;
                $propertyPath = $field;
            } else {
                throw new TypeError('Unsupported field type. Field should be a string.');
            }

            try {
                $propertyValue = $current[$propertyPath];

                $data[$name] = $this->getValue($propertyValue);
            } catch (UnexpectedTypeException $e) {
                // Non existent object in path will be ignored but a wrong path will still throw exceptions
                $data[$name] = null;
            }
        }

        return $data;
    }
}
