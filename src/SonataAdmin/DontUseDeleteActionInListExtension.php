<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use LogicException;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Forbids delete in list/batch unless admin implements WithDeleteActionInList.
 *
 * Project-wide switch: `glavweb_admin.delete_in_list: deny|allow` (default deny).
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
#[AutoconfigureTag(name: 'sonata.admin.extension', attributes: ['global' => true])]
class DontUseDeleteActionInListExtension extends AbstractAdminExtension
{
    public const string MODE_DENY = 'deny';
    public const string MODE_ALLOW = 'allow';

    public function __construct(
        #[Autowire(param: 'glavweb_admin.delete_in_list')]
        private readonly string $mode = self::MODE_DENY,
    ) {
    }

    public function configureActionButtons(AdminInterface $admin, array $list, string $action, ?object $object = null): array
    {
        if ($this->isDenied($admin) && 'list' === $action && \array_key_exists('delete', $list)) {
            $this->throwException($admin);
        }

        return $list;
    }

    public function configureListFields(ListMapper $listMapper): void
    {
        $admin = $listMapper->getAdmin();
        if (!$this->isDenied($admin)) {
            return;
        }

        if (!$listMapper->has('_action')) {
            return;
        }

        $actionField = $listMapper->get('_action');
        /** @var FieldDescriptionInterface $actionField */
        $actions = $actionField->getOption('actions', []);

        if (\array_key_exists('delete', $actions)) {
            $this->throwException($admin);
        }
    }

    public function configureBatchActions(AdminInterface $admin, array $actions): array
    {
        if ($this->isDenied($admin) && \array_key_exists('delete', $actions)) {
            $this->throwException($admin);
        }

        return $actions;
    }

    private function isDenied(AdminInterface $admin): bool
    {
        return self::MODE_ALLOW !== $this->mode && !$admin instanceof WithDeleteActionInList;
    }

    private function throwException(AdminInterface $admin): void
    {
        throw new LogicException(sprintf(
            'Delete action in list is forbidden for admin "%s". Implement %s on the admin or set glavweb_admin.delete_in_list: allow.',
            $admin::class,
            WithDeleteActionInList::class,
        ));
    }
}
