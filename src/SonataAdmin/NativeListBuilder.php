<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use RuntimeException;
use Sonata\AdminBundle\Builder\ListBuilderInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionCollection;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;

/**
 * List builder for native SQL analytics lists.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class NativeListBuilder implements ListBuilderInterface
{
    /**
     * @var string[]
     */
    private array $templates;

    public function __construct(array $templates = [])
    {
        $this->templates = $templates['types']['list'] ?? [];
    }

    public function getBaseList(array $options = []): FieldDescriptionCollection
    {
        return new FieldDescriptionCollection();
    }

    public function buildField(?string $type, FieldDescriptionInterface $fieldDescription): void
    {
        $fieldDescription->setType($type ?? FieldDescriptionInterface::TYPE_STRING);

        $this->fixFieldDescription($fieldDescription);
    }

    public function fixFieldDescription(FieldDescriptionInterface $fieldDescription): void
    {
        $admin = $fieldDescription->getAdmin();

        if (ListMapper::TYPE_ACTIONS === $fieldDescription->getType()
            || '_action' === $fieldDescription->getName()
            || 'actions' === $fieldDescription->getType()
        ) {
            $this->buildActionFieldDescription($fieldDescription);
        }

        if (false !== $fieldDescription->getOption('sortable')) {
            $fieldDescription->setOption('sortable', $fieldDescription->getOption('sortable', true));
            $fieldDescription->setOption('sort_parent_association_mappings', $fieldDescription->getOption('sort_parent_association_mappings', $fieldDescription->getParentAssociationMappings()));
            $fieldDescription->setOption('sort_field_mapping', $fieldDescription->getOption('sort_field_mapping', ['fieldName' => $fieldDescription->getName()]));
        }
        $fieldDescription->setOption('_sort_order', $fieldDescription->getOption('_sort_order', 'ASC'));

        $type = $fieldDescription->getType();
        if (!$type) {
            throw new RuntimeException(sprintf('Please define a type for field `%s` in `%s`', $fieldDescription->getName(), $admin::class));
        }

        $fieldDescription->setOption('code', $fieldDescription->getOption('code', $fieldDescription->getName()));
        $fieldDescription->setOption('label', $fieldDescription->getOption('label', $fieldDescription->getName()));

        if (!$fieldDescription->getTemplate()) {
            $fieldDescription->setTemplate($this->getTemplate($type));
        }
    }

    public function addField(FieldDescriptionCollection $list, ?string $type, FieldDescriptionInterface $fieldDescription): void
    {
        $this->buildField($type, $fieldDescription);
        $fieldDescription->getAdmin()->addListFieldDescription($fieldDescription->getName(), $fieldDescription);

        $list->add($fieldDescription);
    }

    public function buildActionFieldDescription(FieldDescriptionInterface $fieldDescription): FieldDescriptionInterface
    {
        if (null === $fieldDescription->getTemplate()) {
            $fieldDescription->setTemplate('@SonataAdmin/CRUD/list__action.html.twig');
        }

        if (\in_array($fieldDescription->getType(), [null, '_action'], true)) {
            $fieldDescription->setType(ListMapper::TYPE_ACTIONS);
        }

        if (null === $fieldDescription->getOption('name')) {
            $fieldDescription->setOption('name', 'Action');
        }

        if (null === $fieldDescription->getOption('code')) {
            $fieldDescription->setOption('code', 'Action');
        }

        if (null !== $fieldDescription->getOption('actions')) {
            $actions = $fieldDescription->getOption('actions');
            foreach ($actions as $k => $action) {
                if (!isset($action['template'])) {
                    $actions[$k]['template'] = sprintf('@SonataAdmin/CRUD/list__action_%s.html.twig', $k);
                }
            }

            $fieldDescription->setOption('actions', $actions);
        }

        return $fieldDescription;
    }

    private function getTemplate(string $type): ?string
    {
        return $this->templates[$type] ?? null;
    }
}
