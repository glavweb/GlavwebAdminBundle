<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use TypeError;
use RuntimeException;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Builder\DatagridBuilderInterface;
use Sonata\AdminBundle\Datagrid\Datagrid;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\Pager as BasePager;
use Sonata\AdminBundle\Datagrid\PagerInterface;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Datagrid\SimplePager;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\FieldDescription\TypeGuesserInterface;
use Sonata\AdminBundle\Filter\FilterFactoryInterface;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Datagrid builder for native SQL analytics lists (Sonata 4: cannot extend final DatagridBuilder).
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class NativeDatagridBuilder implements DatagridBuilderInterface
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private FilterFactoryInterface $filterFactory,
        private TypeGuesserInterface $guesser,
        private bool $csrfTokenEnabled = true,
    ) {
    }

    public function getBaseDatagrid(AdminInterface $admin, array $values = []): DatagridInterface
    {
        $pager = $this->getPager($admin->getPagerType());

        $defaultOptions = ['validation_groups' => false];
        if ($this->csrfTokenEnabled) {
            $defaultOptions['csrf_protection'] = false;
        }

        $formBuilder = $this->formFactory->createNamedBuilder('filter', FormType::class, [], $defaultOptions);

        $query = $admin->createQuery();
        if (!$query instanceof ProxyQueryInterface) {
            throw new TypeError(sprintf('The admin query MUST implement %s.', ProxyQueryInterface::class));
        }

        return new Datagrid($query, $admin->getList(), $pager, $formBuilder, $values);
    }

    public function addFilter(DatagridInterface $datagrid, ?string $type, FieldDescriptionInterface $fieldDescription): void
    {
        $fieldType = $type ?? StringFilter::class;

        $fieldDescription->setType($fieldType);

        $this->fixFieldDescription($fieldDescription);
        $fieldDescription->getAdmin()->addFilterFieldDescription($fieldDescription->getName(), $fieldDescription);

        $fieldDescription->mergeOption('field_options', ['required' => false]);

        $filter = $this->filterFactory->create($fieldDescription->getName(), $fieldType, $fieldDescription->getOptions());

        if (false !== $filter->getLabel() && !$filter->getLabel()) {
            $filter->setLabel($fieldDescription->getAdmin()->getLabelTranslatorStrategy()->getLabel($fieldDescription->getName(), 'filter', 'label'));
        }

        $datagrid->addFilter($filter);
    }

    public function fixFieldDescription(FieldDescriptionInterface $fieldDescription): void
    {
        $fieldDescription->setOption('code', $fieldDescription->getOption('code', $fieldDescription->getName()));
        $fieldDescription->setOption('name', $fieldDescription->getOption('name', $fieldDescription->getName()));
    }

    private function getPager(string $pagerType): PagerInterface
    {
        switch ($pagerType) {
            case BasePager::TYPE_DEFAULT:
                return new NativePager();

            case BasePager::TYPE_SIMPLE:
                return new SimplePager();

            default:
                throw new RuntimeException(sprintf('Unknown pager type "%s".', $pagerType));
        }
    }
}
