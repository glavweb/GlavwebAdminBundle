<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use Glavweb\AdminBundle\SonataAdmin\FilterCallback;
use Glavweb\FrameworkBundle\Entity\Archivable;
use RuntimeException;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Sonata\Form\Type\BooleanType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Datagrid filter + read-only forms for Archivable entities.
 *
 * Applied automatically to every admin whose model implements Archivable
 * (no `sonata_admin.extensions` entry needed in the app).
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
#[AutoconfigureTag(name: 'sonata.admin.extension', attributes: ['implements' => Archivable::class])]
class ArchivableExtension extends AbstractAdminExtension
{
    public const string FILTER_ARCHIVED = '_archived';

    public function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        if ($datagridMapper->getAdmin()->getDatagridBuilder() instanceof NativeDatagridBuilder) {
            return;
        }

        $datagridMapper->add(self::FILTER_ARCHIVED, CallbackFilter::class, [
            'field_name' => 'archivedAt',
            'field_type' => BooleanType::class,
            'label' => 'label__archived',
            'translation_domain' => 'SonataAdminBundle',
            'callback' => static function (ProxyQueryInterface $queryBuilder, $alias, $field, FilterData $data): bool {
                $value = FilterCallback::value($data);
                if (null === $value) {
                    return false;
                }

                if (BooleanType::TYPE_YES === (int) $value) {
                    $queryBuilder->andWhere($queryBuilder->expr()->isNotNull(sprintf('%s.%s', $alias, $field)));
                }

                if (BooleanType::TYPE_NO === (int) $value) {
                    $queryBuilder->andWhere($queryBuilder->expr()->isNull(sprintf('%s.%s', $alias, $field)));
                }

                return true;
            },
        ]);
    }

    public function configureDefaultFilterValues(AdminInterface $admin, array &$filterValues): void
    {
        if ($admin->getDatagridBuilder() instanceof NativeDatagridBuilder) {
            return;
        }

        $filterValues[self::FILTER_ARCHIVED] = [
            'value' => BooleanType::TYPE_NO,
        ];
    }

    public function configureFormFields(FormMapper $formMapper): void
    {
        $subject = $formMapper->getAdmin()->getSubject();

        if (null === $subject) {
            return;
        }

        if (!$subject instanceof Archivable) {
            throw new RuntimeException('Subject must be an instance of Archivable');
        }

        if (!$subject->isArchived()) {
            return;
        }

        foreach ($formMapper->keys() as $key) {
            $builder = $formMapper->get($key);
            $builder->setDisabled(true);
            $builder->setAttribute('readonly', true);
        }
    }
}
