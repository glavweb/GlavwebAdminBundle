<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use TypeError;
use Sonata\AdminBundle\Datagrid\Pager as BasePager;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;

/**
 * Pager for native SQL queries (Sonata 4: cannot extend final DoctrineORM Pager).
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class NativePager extends BasePager
{
    private int $resultsCount = 0;

    public function getCurrentPageResults(): iterable
    {
        $query = $this->getQuery();
        if (!$query instanceof ProxyQueryInterface) {
            throw new TypeError(sprintf('The pager query MUST implement %s.', ProxyQueryInterface::class));
        }

        return $query->execute();
    }

    public function countResults(): int
    {
        return $this->resultsCount;
    }

    public function init(): void
    {
        $query = $this->getQuery();
        if (!$query instanceof ProxyQueryInterface) {
            throw new TypeError(sprintf('The pager query MUST implement %s.', ProxyQueryInterface::class));
        }

        $this->resultsCount = $this->computeNbResult($query);

        $query->setFirstResult(null);
        $query->setMaxResults(null);

        if (0 === $this->getPage() || 0 === $this->getMaxPerPage()) {
            $this->setLastPage(0);
        } elseif (0 === $this->countResults()) {
            $this->setLastPage(1);
        } else {
            $offset = ($this->getPage() - 1) * $this->getMaxPerPage();

            $this->setLastPage((int) ceil($this->countResults() / $this->getMaxPerPage()));

            $query->setFirstResult($offset ?? 0);
            $query->setMaxResults($this->getMaxPerPage());
        }
    }

    private function computeNbResult(ProxyQueryInterface $query): int
    {
        if (!$query instanceof NativeQueryBuilder) {
            return \count(iterator_to_array($query->execute(), false));
        }

        $countQuery = $query->getConnection()->createQueryBuilder();
        $countSubQuery = clone $query;
        $countSubQuery->resetOrderBy();

        if (\count($query->getParameters()) > 0) {
            $countQuery->setParameters($query->getParameters(), $query->getParameterTypes());
        }

        $countQuery
            ->select('count(subquery.*) as cnt')
            ->from(sprintf('(%s)', $countSubQuery->getSQL()), 'subquery');

        return (int) $countQuery->executeQuery()->fetchOne();
    }
}
