<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use LogicException;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Forbids delete in list/batch unless admin implements WithDeleteActionInList.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
#[AutoconfigureTag(name: 'sonata.admin.extension', attributes: ['global' => true])]
class DontUseDeleteActionInListExtension extends AbstractAdminExtension
{
    public function configureActionButtons(AdminInterface $admin, array $list, string $action, ?object $object = null): array
    {
        if (!$admin instanceof WithDeleteActionInList && 'list' === $action && \array_key_exists('delete', $list)) {
            $this->throwException();
        }

        return $list;
    }

    public function configureListFields(ListMapper $listMapper): void
    {
        if ($listMapper->getAdmin() instanceof WithDeleteActionInList) {
            return;
        }

        if (!$listMapper->has('_action')) {
            return;
        }

        $actionField = $listMapper->get('_action');
        /** @var FieldDescriptionInterface $actionField */
        $actions = $actionField->getOption('actions', []);

        if (\array_key_exists('delete', $actions)) {
            $this->throwException();
        }
    }

    public function configureBatchActions(AdminInterface $admin, array $actions): array
    {
        if (!$admin instanceof WithDeleteActionInList && \array_key_exists('delete', $actions)) {
            $this->throwException();
        }

        return $actions;
    }

    private function throwException(): void
    {
        throw new LogicException('Добавлять действие удаления в список запрещено');
    }
}
