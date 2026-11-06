<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Maker;

use ReflectionClass;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\ManagerRegistry;
use Glavweb\AdminBundle\Maker\Helper\Helper;
use Glavweb\AdminBundle\Maker\Manipulator\AdminServicesManipulator;
use Glavweb\UploaderBundle\Mapping\Attribute\UploadableField;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class MakeAdmin.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class MakeAdmin extends AbstractMaker
{
    private const string USER_ENTITY = 'App\\Entity\\User\\User';

    /**
     * @var list<string>
     */
    private const array IDENTIFIER_PRIORITY = [
        'name',
        'title',
        'fullName',
        'subject',
        'question',
        'message',
        'description',
        'body',
    ];

    /**
     * @var list<string>
     */
    private const array LIST_SKIP_FIELDS = [
        'company',
        'createdAt',
        'updatedAt',
    ];

    private EntityManager $entityManager;

    private string $adminServicesFile;

    /**
     * MakeAdmin constructor.
     */
    public function __construct(ManagerRegistry $doctrine, string $adminServicesFile = 'config/admin.yaml')
    {
        /** @var EntityManager $entityManager */
        $entityManager = $doctrine->getManager();
        $this->entityManager = $entityManager;
        $this->adminServicesFile = $adminServicesFile;
    }

    public static function getCommandName(): string
    {
        return 'make:admin';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription('Creates a new admin class')
            ->addArgument('model', InputArgument::REQUIRED, 'The name of the model class (e.g. <fg=yellow>Article</>)')
            ->addOption('controller')
            ->addOption('manager')
            ->addOption('author', null, InputOption::VALUE_OPTIONAL, 'The author of class')
            ->addOption('author-email', null, InputOption::VALUE_OPTIONAL, 'The author e-mail of class')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            AbstractAdmin::class,
            'SonataAdminBundle'
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $modelArgument = $input->getArgument('model');
        $hasController = $input->getOption('controller');
        $hasManager = $input->getOption('manager');

        $modelClass = $generator->createClassNameDetails(
            $modelArgument,
            'Entity\\',
            ''
        );

        $adminClass = $generator->createClassNameDetails(
            $modelArgument,
            'Admin\\',
            'Admin'
        );

        if ($hasController) {
            $controllerClass = $generator->createClassNameDetails(
                $modelArgument,
                'Controller\\Admin\\',
                'Controller'
            );
        } else {
            $controllerClass = $generator->createClassNameDetails(
                'CRUD',
                'Controller\\Admin\\',
                'Controller'
            );
        }

        $managerClass = null;
        if ($hasManager) {
            $managerClass = $generator->createClassNameDetails(
                $modelArgument,
                'Model\\',
                'Manager'
            );
        }

        $serviceId = Container::underscore(str_replace('\\', '.', $modelClass->getShortName()));
        $normalizedServiceId = str_replace('.', '_', $serviceId);

        $fields = $this->getFieldsAndAssociations($modelClass->getFullName());
        $listFieldNames = $this->configureListFieldMetadata($fields);
        $baseRouteName = $normalizedServiceId;
        $baseRoutePattern = str_replace('_', '-', $baseRouteName);
        $translationDomain = $normalizedServiceId;

        /*
         * Create Admin class
         */
        $generator->generateClass(
            $adminClass->getFullName(),
            __DIR__.'/../../templates/skeleton/admin/Admin.tpl.php',
            [
                'modelClass' => $modelClass,
                'adminClass' => $adminClass,
                'fields' => $fields,
                'listFieldNames' => $listFieldNames,
                'baseRoutePattern' => $baseRoutePattern,
                'baseRouteName' => $baseRouteName,
                'translationDomain' => $translationDomain,
                'managerClass' => $managerClass,
                'author' => $input->getOption('author'),
                'authorEmail' => $input->getOption('author-email'),
            ]
        );

        if ($hasController) {
            /*
             * Create Controller class
             */
            $generator->generateClass(
                $controllerClass->getFullName(),
                __DIR__.'/../../templates/skeleton/admin/Controller.tpl.php',
                [
                    'author' => $input->getOption('author'),
                    'authorEmail' => $input->getOption('author-email'),
                ]
            );
        }

        /**
         * Create service.
         */
        $servicesManipulator = new AdminServicesManipulator($this->adminServicesFile);

        $group = 'label_group_'.strtolower($this->getUnderscore(current(explode('\\', $modelClass->getRelativeName()))));
        $servicesManipulator->addResource(
            $serviceId,
            $modelClass->getFullName(),
            $adminClass->getFullName(),
            $controllerClass->getFullName(),
            'orm',
            $group,
            $managerClass ? $managerClass->getFullName() : null
        );

        /**
         * Create translation.
         */
        $targetPath = sprintf(
            'translations/%s.ru.yaml',
            Container::underscore(str_replace('\\', '/', $modelClass->getRelativeName()))
        );

        $generator->generateFile(
            $targetPath,
            __DIR__.'/../../templates/skeleton/admin/translation.tpl.php',
            [
                'modelClass' => $modelClass,
                'fields' => $fields,
                'normalizedServiceId' => $normalizedServiceId,
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
    }

    /**
     * @throws MappingException
     */
    private function getFieldsAndAssociations(string $modelClass): array
    {
        /** @var class-string<object> $modelClass */
        $metadata = $this->entityManager->getClassMetadata($modelClass);

        $fields = [];
        $fieldNames = $metadata->getFieldNames();
        foreach ($fieldNames as $fieldName) {
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            $type = $fieldMapping['type'];
            $columnName = $fieldMapping['columnName'];
            $enumClass = null;

            $translation = isset($fieldMapping['options']['comment']) ?
                $fieldMapping['options']['comment'] :
                str_replace('_', ' ', ucfirst($columnName))
            ;

            $enumClass = Helper::enumClassFromFieldMapping($fieldMapping);

            $fields[$fieldName] = $this->getFiledData(
                $type,
                $columnName,
                $translation,
                true,
                null,
                $enumClass
            );
        }

        $associationMappings = $metadata->getAssociationMappings();
        foreach ($associationMappings as $associationMapping) {
            $fieldName = $associationMapping['fieldName'];
            $type = $associationMapping['type'];
            $columnName = $this->getUnderscore($associationMapping['fieldName']);
            $translation = $associationMapping['fieldName'];

            $typeString = '';
            switch ($type) {
                case ClassMetadata::ONE_TO_MANY:
                    $typeString = 'one_to_many';
                    break;

                case ClassMetadata::MANY_TO_MANY:
                    $typeString = 'many_to_many';
                    break;

                case ClassMetadata::MANY_TO_ONE:
                    $typeString = 'many_to_one';
                    break;

                case ClassMetadata::ONE_TO_ONE:
                    $typeString = 'one_to_one';
                    break;
            }

            if ($typeString) {
                // TODO Определить
                $translationDomain = null;

                $targetEntity = $associationMapping['targetEntity'] ?? null;
                $fields[$fieldName] = $this->getFiledData(
                    $typeString,
                    $columnName,
                    $translation,
                    true,
                    $translationDomain,
                    null,
                    null,
                    null,
                    is_string($targetEntity) ? $targetEntity : null
                );
            }
        }

        // File properties
        /** @var class-string<object> $modelClass */
        $reflectionClass = new ReflectionClass($modelClass);
        $properties = $reflectionClass->getProperties();
        foreach ($properties as $property) {
            $uploadableAttributes = $property->getAttributes(UploadableField::class);
            if ([] === $uploadableAttributes) {
                continue;
            }

            /** @var UploadableField $uploadableField */
            $uploadableField = $uploadableAttributes[0]->newInstance();

            $fields[$property->getName()] = $this->getFiledData(
                'file',
                null,
                null,
                false,
                null,
                null,
                null,
                $uploadableField->getMapping()
            );
        }

        return $fields;
    }

    private function getUnderscore(string $underscore): string
    {
        $underscore = (string) current(array_slice(explode('\\', $underscore), -1));

        return ltrim(strtolower((string) preg_replace('/[A-Z]/', '_$0', $underscore)), '_');
    }

    private function getRequiredFiled(string $class, string $fieldName): bool
    {
        /** @var class-string<object> $class */
        $reflectionClass = new ReflectionClass($class);
        $property = $reflectionClass->getProperty($fieldName);

        $columnAttributes = $property->getAttributes(Column::class);
        if ([] !== $columnAttributes) {
            $columnAnnotation = $columnAttributes[0]->newInstance();

            return !$columnAnnotation->nullable;
        }

        return true;
    }

    private function getFiledData(
        string $type,
        ?string $columnName,
        ?string $translation,
        ?bool $required = true,
        ?string $translationDomain = null,
        ?string $enumClass = null,
        ?string $fileNameProperty = null,
        ?string $mapping = null,
        ?string $targetEntity = null,
    ): array {
        return [
            'type' => $type,
            'column_name' => $columnName,
            'translation' => $translation,
            'required' => $required,
            'translation_domain' => $translationDomain,
            'enum_class' => $enumClass,
            'file_name_property' => $fileNameProperty,
            'mapping' => $mapping,
            'target_entity' => $targetEntity,
            'is_user' => false,
            'in_list' => false,
            'identifier' => false,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return list<string>
     */
    private function configureListFieldMetadata(array &$fields): array
    {
        foreach ($fields as &$field) {
            $target = $field['target_entity'] ?? null;
            $field['is_user'] = is_string($target) && is_a($target, self::USER_ENTITY, true);
            $field['identifier'] = false;
            $field['in_list'] = false;
        }
        unset($field);

        $identifier = $this->pickListIdentifier($fields);
        if (null !== $identifier) {
            $fields[$identifier]['identifier'] = true;
            $fields[$identifier]['in_list'] = true;
        }

        foreach ($fields as $fieldName => &$field) {
            if ($field['in_list']) {
                continue;
            }

            $field['in_list'] = $this->shouldAppearInList($fieldName, $field, $identifier);
        }
        unset($field);

        return $this->orderedListFieldNames($fields);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function pickListIdentifier(array $fields): ?string
    {
        foreach (self::IDENTIFIER_PRIORITY as $name) {
            if (isset($fields[$name]) && $this->isIdentifierCandidate($name, $fields[$name])) {
                return $name;
            }
        }

        foreach ($fields as $name => $field) {
            if ($this->isStringIdentifierCandidate($name, $field)) {
                return $name;
            }
        }

        foreach ($fields as $name => $field) {
            if (\in_array($name, ['createdAt', 'updatedAt', 'id', 'company'], true)) {
                continue;
            }

            if (\in_array($field['type'], ['datetime', 'date'], true) && $this->isIdentifierCandidate($name, $field)) {
                return $name;
            }
        }

        foreach ($fields as $name => $field) {
            if (isset($field['enum_class']) && $this->isIdentifierCandidate($name, $field)) {
                return $name;
            }
        }

        if (isset($fields['id'])) {
            return 'id';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isIdentifierCandidate(string $name, array $field): bool
    {
        if (\in_array($name, self::LIST_SKIP_FIELDS, true)) {
            return false;
        }

        if (!empty($field['is_user'])) {
            return false;
        }

        return !\in_array($field['type'], ['file', 'one_to_many', 'many_to_many'], true);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isStringIdentifierCandidate(string $name, array $field): bool
    {
        if (\in_array($name, ['id', ...self::LIST_SKIP_FIELDS], true)) {
            return false;
        }

        return \in_array($field['type'], ['string', 'text'], true);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function shouldAppearInList(string $name, array $field, ?string $identifier): bool
    {
        if (\in_array($name, self::LIST_SKIP_FIELDS, true)) {
            return false;
        }

        if ('id' === $name) {
            return false;
        }

        if (\in_array($field['type'], ['one_to_many', 'many_to_many'], true)) {
            return false;
        }

        if ('text' === $field['type'] && $name !== $identifier) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return list<string>
     */
    private function orderedListFieldNames(array $fields): array
    {
        $files = [];
        $identifier = [];
        $rest = [];

        foreach ($fields as $name => $field) {
            if (empty($field['in_list'])) {
                continue;
            }

            if ('file' === $field['type']) {
                $files[] = $name;
            } elseif (!empty($field['identifier'])) {
                $identifier[] = $name;
            } else {
                $rest[] = $name;
            }
        }

        return array_merge($files, $identifier, $rest);
    }
}
