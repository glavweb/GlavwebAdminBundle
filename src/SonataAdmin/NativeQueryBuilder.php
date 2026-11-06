<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use stdClass;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query\ParameterTypeInferer;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;

/**
 * Native SQL proxy query. Uses composition because DBAL QueryBuilder return types
 * conflict with Sonata ProxyQueryInterface (e.g. getFirstResult(): int vs ?int).
 */
class NativeQueryBuilder implements ProxyQueryInterface
{
    private Connection $connection;

    private QueryBuilder $queryBuilder;

    private ?ItemProcessorInterface $itemProcessor;

    private ?string $sortBy = null;

    private ?string $sortOrder = null;

    public function __construct(Connection $connection, ?ItemProcessorInterface $itemProcessor = null)
    {
        $this->connection = $connection;
        $this->queryBuilder = new QueryBuilder($connection);
        $this->itemProcessor = $itemProcessor;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function __clone()
    {
        $this->queryBuilder = clone $this->queryBuilder;
    }

    /**
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        $result = $this->queryBuilder->{$name}(...$args);

        return $result === $this->queryBuilder ? $this : $result;
    }

    /**
     * @return iterable<object>
     */
    public function execute(): iterable
    {
        $queryBuilder = clone $this->queryBuilder;

        if ($this->getSortBy()) {
            $queryBuilder->addOrderBy($this->getSortBy(), $this->getSortOrder());
        }

        $result = $queryBuilder->executeQuery()->fetchAllAssociative();
        $itemProcessor = $this->itemProcessor;

        return array_map(static function (array $item) use ($itemProcessor): stdClass {
            if ($itemProcessor) {
                $itemProcessor->processItem($item);
            }

            return (object) $item;
        }, $result);
    }

    public function setSortBy(array $parentAssociationMappings, array $fieldMapping): self
    {
        $this->sortBy = $fieldMapping['fieldName'];

        return $this;
    }

    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    public function setSortOrder(string $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getSortOrder(): ?string
    {
        return $this->sortOrder;
    }

    public function setFirstResult(?int $firstResult): self
    {
        $this->queryBuilder->setFirstResult($firstResult ?? 0);

        return $this;
    }

    public function getFirstResult(): ?int
    {
        return $this->queryBuilder->getFirstResult();
    }

    public function setMaxResults(?int $maxResults): self
    {
        $this->queryBuilder->setMaxResults($maxResults);

        return $this;
    }

    public function getMaxResults(): ?int
    {
        return $this->queryBuilder->getMaxResults();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getSQL(): string
    {
        return $this->queryBuilder->getSQL();
    }

    public function getParameters(): array
    {
        return $this->queryBuilder->getParameters();
    }

    public function getParameterTypes(): array
    {
        return $this->queryBuilder->getParameterTypes();
    }

    public function setParameters(array $params, array $types = []): self
    {
        $this->queryBuilder->setParameters($params, $types);

        return $this;
    }

    public function resetOrderBy(): self
    {
        $this->queryBuilder->resetOrderBy();

        return $this;
    }

    public function addOrderBy(string $sort, ?string $order = null): self
    {
        $this->queryBuilder->addOrderBy($sort, $order);

        return $this;
    }

    public function executeQuery(): Result
    {
        return $this->queryBuilder->executeQuery();
    }

    public function setParameter(string|int $key, mixed $value, ParameterType|Type|ArrayParameterType|string $type = ParameterType::STRING): self
    {
        if (\is_int($key) && $key < 0) {
            throw new \InvalidArgumentException('Ключ параметра не может быть отрицательным');
        }

        if (\func_num_args() < 3) {
            $inferred = ParameterType::tryFrom((int) ParameterTypeInferer::inferType($value));
            if (null === $inferred) {
                throw new \LogicException('Невозможно определить тип параметра');
            }
            $type = $inferred;
        }

        $this->queryBuilder->setParameter($key, $value, $type);

        return $this;
    }
}
