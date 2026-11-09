<?php
$hasOneToManyType = false;
$hasManyToManyType = false;
$hasDateType = false;
$hasDatetimeType = false;
$hasFileType = false;
$hasEnumType = false;
$enumShortNames = [];

foreach ($fields as $fieldName => $fieldOptions) {
    $fieldType = $fieldOptions['type'];

    if ('one_to_many' == $fieldType) {
        $hasOneToManyType = true;
    } elseif ('many_to_many' == $fieldType) {
        $hasManyToManyType = true;
    } elseif ('date' == $fieldType) {
        $hasDateType = true;
    } elseif ('datetime' == $fieldType) {
        $hasDatetimeType = true;
    } elseif ('file' == $fieldType) {
        $hasFileType = true;
    } elseif (isset($fieldOptions['enum_class'])) {
        $hasEnumType = true;
        $enumFqcn = $fieldOptions['enum_class'];
        $enumShortNames[$fieldName] = ($pos = strrpos($enumFqcn, '\\')) !== false
            ? substr($enumFqcn, $pos + 1)
            : $enumFqcn;
    }
}
?>
<?php echo "<?php\n\n"; ?>
declare(strict_types=1);

namespace <?php echo $namespace; ?>;

use App\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
<?php if ($hasDateType || $hasDatetimeType) { ?>
use Sonata\Form\Type\DateRangePickerType;
<?php } ?>
<?php if ($hasDateType) { ?>
use Sonata\Form\Type\DatePickerType;
use Sonata\DoctrineORMAdminBundle\Filter\DateRangeFilter;
<?php } ?>
<?php if ($hasDatetimeType) { ?>
use Sonata\Form\Type\DateTimePickerType;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
<?php } ?>
<?php if ($hasOneToManyType) { ?>
use Sonata\Form\Type\CollectionType;
<?php } ?>
<?php if ($hasManyToManyType) { ?>
use Sonata\AdminBundle\Form\Type\ModelType;
<?php } ?>
<?php if ($hasFileType) { ?>
use Glavweb\UploaderDropzoneBundle\Form\ImageType;
<?php } ?>
<?php if ($hasEnumType) { ?>
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
<?php } ?>
<?php
foreach ($fields as $fieldName => $fieldOptions) {
    $fieldType = $fieldOptions['type'];
    if (isset($fieldOptions['enum_class'])) {
        echo 'use '.$fieldOptions['enum_class'].';'."\n";
    }
}
?>
<?php if ($managerClass) { ?>
use Glavweb\FrameworkBundle\Exception\DeleteEntityException;
use Glavweb\FrameworkBundle\Exception\InvalidEntityException;
use <?php echo $managerClass->getFullName(); ?>;
<?php } ?>
use <?php echo $modelClass->getFullName(); ?>;

/**
 * Class <?php echo $class_name."\n"; ?>
<?php
if ($author) {
    $authorFull = $author;
    if ($authorEmail) {
        $authorFull .= ' <'.$authorEmail.'>';
    }

    echo " *\n";
    echo " * @author $authorFull\n";
}
?>
 */
class <?php echo $class_name; ?> extends AbstractAdmin
{
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return '<?php echo $baseRoutePattern; ?>';
    }

    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return '<?php echo $baseRouteName; ?>';
    }

<?php if ($managerClass) { ?>
    protected <?php echo $managerClass->getShortName(); ?> $manager;
<?php } ?>

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        parent::configureRoutes($collection);

        $collection->remove('show');
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
<?php
    foreach ($fields as $fieldName => $fieldOptions) {
        $fieldType = $fieldOptions['type'];

        if ('id' == $fieldName) {
            continue;
        }

        if (in_array($fieldType, ['file', 'one_to_many', 'many_to_many'])) {
            continue;
        }

        if (isset($fieldOptions['enum_class'])) {
            $enumShort = $enumShortNames[$fieldName];
            echo "            ->add('$fieldName', ChoiceFilter::class, [\n";
            echo "                'field_type' => ChoiceType::class,\n";
            echo "                'field_options' => [\n";
            echo "                    'choices'  => $enumShort::getFormChoices(),\n";
            echo "                    'multiple' => true\n";
            echo "                ]\n";
            echo "            ])\n";
        } elseif ('date' == $fieldType) {
            echo "            ->add('$fieldName', DateRangeFilter::class, [\n";
            echo "                'field_type' => DateRangePickerType::class,\n";
            echo "                'field_options' => [\n";
            echo "                    'field_options' => [\n";
            echo "                        'format' => 'dd.MM.yyyy'\n";
            echo "                    ]\n";
            echo "                ]\n";
            echo "            ])\n";
        } elseif ('datetime' == $fieldType) {
            echo "            ->add('$fieldName', DateTimeRangeFilter::class, [\n";
            echo "                'field_type' => DateRangePickerType::class,\n";
            echo "                'field_options' => [\n";
            echo "                    'field_options' => [\n";
            echo "                        'format' => 'dd.MM.yyyy'\n";
            echo "                    ]\n";
            echo "                ]\n";
            echo "            ])\n";
        } else {
            echo "            ->add('$fieldName')\n";
        }
    }
?>
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
<?php
    $listFieldNames = $listFieldNames ?? [];

foreach ($listFieldNames as $fieldName) {
    $fieldOptions = $fields[$fieldName];
    $fieldType = $fieldOptions['type'];
    $method = !empty($fieldOptions['identifier']) ? 'addIdentifier' : 'add';

    if (!empty($fieldOptions['is_user'])) {
        echo "            ->$method('$fieldName', 'user')\n";

        continue;
    }

    if ('file' == $fieldType) {
        $mapping = $fieldOptions['mapping'] ?? $fieldName;
        $thumbnailFilter = $mapping.'_320';
        echo "            ->$method('$fieldName', 'glavweb_image', [\n";
        echo "                'thumbnail_filter' => '$thumbnailFilter',\n";
        echo "            ])\n";

        continue;
    }

    if (isset($fieldOptions['enum_class'])) {
        $enumShort = $enumShortNames[$fieldName];
        echo "            ->$method('$fieldName', 'enum', [\n";
        echo "                'enum' => $enumShort::class,\n";
        echo "            ])\n";

        continue;
    }

    if ('boolean' == $fieldType) {
        echo "            ->$method('$fieldName', null, ['editable' => true])\n";

        continue;
    }

    if ('datetime' == $fieldType) {
        echo "            ->$method('$fieldName', 'datetime', ['format' => 'd.m.Y H:i'])\n";

        continue;
    }

    if ('date' == $fieldType) {
        echo "            ->$method('$fieldName', 'datetime', ['format' => 'd.m.Y'])\n";

        continue;
    }

    echo "            ->$method('$fieldName')\n";
}
?>
            ->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                ]
            ])
        ;
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        /** @var <?php echo $modelClass->getShortName(); ?> $<?php echo lcfirst($modelClass->getShortName()); ?> */
<?php if ($hasFileType) { ?>
        $<?php echo lcfirst($modelClass->getShortName()); ?> = $this->getSubject();

        $formMapper->with('group.default', ['class' => 'col-md-8 header-hidden', 'label' => ''])->end();
        $formMapper->with('group.file', ['class' => 'col-md-4 header-hidden', 'label' => ''])->end();
<?php } else { ?>
        // $<?php echo lcfirst($modelClass->getShortName()); ?> = $this->getSubject();

        $formMapper->with('group.default', ['class' => 'col-md-12 header-hidden', 'label' => ''])->end();
<?php } ?>

        $formMapper
            ->with('group.default')
<?php
    foreach ($fields as $fieldName => $fieldOptions) {
        $fieldType = $fieldOptions['type'];

        if ('id' == $fieldName) {
            continue;
        }

        if (in_array($fieldType, ['file'])) {
            continue;
        }

        if (isset($fieldOptions['enum_class'])) {
            $enumShort = $enumShortNames[$fieldName];
            echo "                ->add('$fieldName', ChoiceType::class, [\n";
            echo "                    'choices' => $enumShort::getFormChoices(),\n";
            echo "                ])\n";
        } elseif ('one_to_many' == $fieldType) {
            echo "                ->add('$fieldName', CollectionType::class, [\n";
            echo "                    'required'     => false,\n";
            echo "                    'by_reference' => false,\n";
            echo "                    'type_options' => [\n";
            echo "                        'delete'   => true,\n";
            echo "                        'required' => true,\n";
            echo "                    ],\n";
            echo "                ], [\n";
            echo "                    'edit'         => 'inline',\n";
            echo "                    'inline'       => 'table', // table = thead; rows = labeled cards\n";
            echo "                    'allow_delete' => true,\n";
            echo "                ])\n";
        } elseif ('many_to_many' == $fieldType) {
            echo "                ->add('$fieldName', ModelType::class, [\n";
            echo "                    'multiple' => true,\n";
            echo "                ])\n";
        } elseif ('date' == $fieldType) {
            echo "                ->add('$fieldName', DatePickerType::class, [\n";
            echo "                    'datepicker_options' => [\n";
            echo "                        'useCurrent' => false,\n";
            echo "                    ],\n";
            echo "                    'format' => 'dd.MM.yyyy',\n";
            echo "                ])\n";
        } elseif ('datetime' == $fieldType) {
            echo "                ->add('$fieldName', DateTimePickerType::class, [\n";
            echo "                    'datepicker_options' => [\n";
            echo "                        'useCurrent' => false,\n";
            echo "                        'display' => [\n";
            echo "                            'components' => [\n";
            echo "                                'seconds' => false,\n";
            echo "                            ],\n";
            echo "                        ],\n";
            echo "                    ],\n";
            echo "                    'format' => 'dd.MM.yyyy H:m',\n";
            echo "                ])\n";
        } else {
            echo "                ->add('$fieldName')\n";
        }
    }
?>
            ->end()
<?php
    if ($hasFileType) {
        echo "            ->with('group.file')\n";
        foreach ($fields as $fieldName => $fieldOptions) {
            $fieldType = $fieldOptions['type'];

            if ('file' != $fieldType) {
                continue;
            }

            echo "                ->add('$fieldName', ImageType::class, [\n";
            if ($fieldOptions['required']) {
                echo "                    'required' => !$".lcfirst($modelClass->getShortName()).' || !$'.lcfirst($modelClass->getShortName()).'->get'.ucfirst($fieldName)."(),\n";
            } else {
                echo "                    'required' => false,\n";
            }
            if (!empty($fieldOptions['mapping'])) {
                echo "                    'context' => '".$fieldOptions['mapping']."',\n";
            }
            echo '                    \'help\' => ($'.lcfirst($modelClass->getShortName()).' ? $this->getHelpForImage($'.lcfirst($modelClass->getShortName()).', \''.$fieldName.'\') : \'\')'."\n";
            echo "                ])\n";
        }
        echo "            ->end()\n";
    }
?>
        ;
    }

<?php if ($managerClass) { ?>
    public function createObject(object $<?php echo lcfirst($modelClass->getShortName()); ?>): object
    {
        try {
            $this->manager->create($<?php echo lcfirst($modelClass->getShortName()); ?>, $this->getUser());
        } catch (InvalidEntityException $e) {
            throw $this->createModelManagerExceptionFrom($e);
        }

        return $<?php echo lcfirst($modelClass->getShortName()); ?>;
    }

    public function updateObject(object $<?php echo lcfirst($modelClass->getShortName()); ?>): object
    {
        try {
            $this->manager->update($<?php echo lcfirst($modelClass->getShortName()); ?>, $this->getUser());
        } catch (InvalidEntityException $e) {
            throw $this->createModelManagerExceptionFrom($e);
        }

        return $<?php echo lcfirst($modelClass->getShortName()); ?>;
    }

    public function deleteObject(object $<?php echo lcfirst($modelClass->getShortName()); ?>): void
    {
        try {
            $this->manager->delete($<?php echo lcfirst($modelClass->getShortName()); ?>);
        } catch (DeleteEntityException $e) {
            throw $this->createModelManagerExceptionFrom($e);
        }
    }

    public function setManager(<?php echo $managerClass->getShortName(); ?> $manager): void
    {
        $this->manager = $manager;
    }
<?php } ?>
}
