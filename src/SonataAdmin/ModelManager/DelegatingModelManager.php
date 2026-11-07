<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\ModelManager;

use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Model\ModelManagerInterface;

/**
 * Decorates Sonata ORM ModelManager; create/update/delete go to Admin createObject/updateObject/deleteObject.
 *
 * Each sonata.admin gets its own non-shared instance (see AdminDelegatingModelManagerPass).
 * Bind the admin in AbstractAdmin::configure() via bind().
 */
final class DelegatingModelManager implements ModelManagerInterface
{
    private ?object $admin = null;

    public function __construct(
        private readonly ModelManagerInterface $orm,
    ) {
    }

    public function bind(object $admin): void
    {
        $this->admin = $admin;
    }

    public function create(object $object): void
    {
        if ($this->admin && method_exists($this->admin, 'createObject')) {
            $this->admin->createObject($object);

            return;
        }

        $this->orm->create($object);
    }

    public function update(object $object): void
    {
        if ($this->admin && method_exists($this->admin, 'updateObject')) {
            $this->admin->updateObject($object);

            return;
        }

        $this->orm->update($object);
    }

    public function delete(object $object): void
    {
        if ($this->admin && method_exists($this->admin, 'deleteObject')) {
            $this->admin->deleteObject($object);

            return;
        }

        $this->orm->delete($object);
    }

    public function findBy(string $class, array $criteria = []): array
    {
        return $this->orm->findBy($class, $criteria);
    }

    public function findOneBy(string $class, array $criteria = []): ?object
    {
        return $this->orm->findOneBy($class, $criteria);
    }

    public function find(string $class, $id): ?object
    {
        return $this->orm->find($class, $id);
    }

    public function batchDelete(string $class, ProxyQueryInterface $query): void
    {
        $this->orm->batchDelete($class, $query);
    }

    public function createQuery(string $class): ProxyQueryInterface
    {
        return $this->orm->createQuery($class);
    }

    public function getIdentifierValues(object $model): array
    {
        return $this->orm->getIdentifierValues($model);
    }

    public function getIdentifierFieldNames(string $class): array
    {
        return $this->orm->getIdentifierFieldNames($class);
    }

    public function getNormalizedIdentifier(object $model): ?string
    {
        return $this->orm->getNormalizedIdentifier($model);
    }

    public function getUrlSafeIdentifier(object $model): ?string
    {
        return $this->orm->getUrlSafeIdentifier($model);
    }

    public function reverseTransform(object $object, array $array = []): void
    {
        $this->orm->reverseTransform($object, $array);
    }

    public function supportsQuery(object $query): bool
    {
        return $this->orm->supportsQuery($query);
    }

    public function executeQuery(object $query)
    {
        return $this->orm->executeQuery($query);
    }

    public function getExportFields(string $class): array
    {
        return $this->orm->getExportFields($class);
    }

    public function addIdentifiersToQuery(string $class, ProxyQueryInterface $query, array $idx): void
    {
        $this->orm->addIdentifiersToQuery($class, $query, $idx);
    }
}
