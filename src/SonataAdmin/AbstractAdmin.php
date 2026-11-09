<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use BackedEnum;
use ReflectionClass;
use RuntimeException;
use LogicException;
use Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Glavweb\FrameworkBundle\Exception\InvalidEntityException;
use Glavweb\AdminBundle\SonataAdmin\AdvancedFilter\AdvancedDatagridMapper;
use Glavweb\AdminBundle\SonataAdmin\AdvancedFilter\AdvancedFilterBag;
use Glavweb\AdminBundle\SonataAdmin\AdvancedFilter\AdvancedFilterBagBuilder;
use Glavweb\AdminBundle\SonataAdmin\AdvancedFilter\AdvancedFilterContext;
use Glavweb\AdminBundle\SonataAdmin\ModelManager\DelegatingModelManager;
use Glavweb\ExtraBundle\Http\RequestBag;
use Glavweb\FrameworkBundle\Entity\Archivable;
use Glavweb\FrameworkBundle\Model\ArchiveManager;
use Glavweb\FrameworkBundle\Model\DeletionManager;
use Sonata\Form\Type\BooleanType;
use Psr\Container\ContainerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin as BaseAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints\Image;
use Throwable;

/**
 * Reusable Sonata 4 admin base (request fallback, locator helpers, list/filter UX).
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
abstract class AbstractAdmin extends BaseAdmin
{
    public const FORM_GROUP = 'Form';

    /**
     * Service locator injected by the project (not the full container).
     */
    private ?ContainerInterface $container = null;

    private ?AdvancedFilterBagBuilder $advancedFilterBagBuilder = null;

    protected function getAccessMapping(): array
    {
        $accessMapping = parent::getAccessMapping();

        if (!isset($accessMapping['autocomplete'])) {
            $accessMapping['autocomplete'] = 'AUTOCOMPLETE';
        }

        if (!isset($accessMapping['archive'])) {
            $accessMapping['archive'] = 'DELETE';
        }

        if (!isset($accessMapping['unarchive'])) {
            $accessMapping['unarchive'] = 'DELETE';
        }

        return $accessMapping;
    }

    public function getHelpArticle(): ?string
    {
        return null;
    }

    protected function configure(): void
    {
        parent::configure();

        $mm = $this->getModelManager();
        if ($mm instanceof DelegatingModelManager) {
            $mm->bind($this);
        }

        $this->ensureHttpRequest();
        $this->getAdvancedFilterBagBuilder()->reset();
    }

    /**
     * Расширенные фильтры (страница advanced_filter). Пусто = кнопка/роут не появляются.
     */
    protected function configureAdvancedDatagridFilters(AdvancedDatagridMapper $datagridMapper): void
    {
    }

    /**
     * Attributes для AdvancedFilterContext (например company).
     *
     * @return array<string, mixed>
     */
    protected function getAdvancedFilterContextAttributes(): array
    {
        return [];
    }

    public function createAdvancedFilterContext(): AdvancedFilterContext
    {
        return new AdvancedFilterContext($this, $this->getAdvancedFilterContextAttributes());
    }

    public function hasAdvancedFilters(): bool
    {
        return $this->getAdvancedFilterBagBuilder()->hasFilters();
    }

    public function getAdvancedFilterBag(): AdvancedFilterBag
    {
        return $this->getAdvancedFilterBagBuilder()->getBag();
    }

    /**
     * @return list<string>
     */
    public function getActiveAdvancedFilterLabels(): array
    {
        if (!$this->hasAdvancedFilters()) {
            return [];
        }

        return $this->getAdvancedFilterBagBuilder()->getActiveLabels(
            $this->getDatagrid()->getValues(),
            $this->getLabelTranslatorStrategy(),
            $this->getTranslationDomain(),
            fn (string $id, array $params, string $domain): string => $this->trans($id, $params, $domain),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilterParametersWithoutAdvanced(): array
    {
        return $this->getAdvancedFilterBagBuilder()->stripAdvancedParameters($this->getFilterParameters());
    }

    private function getAdvancedFilterBagBuilder(): AdvancedFilterBagBuilder
    {
        return $this->advancedFilterBagBuilder ??= new AdvancedFilterBagBuilder(
            fn (AdvancedDatagridMapper $mapper) => $this->configureAdvancedDatagridFilters($mapper),
        );
    }

    /**
     * True when the list has any active filter that should be announced to the user.
     */
    public function hasDisplayableFilters(): bool
    {
        $parentAssociation = $this->isChild() ? $this->getParentAssociationMapping() : null;

        foreach ($this->getDatagrid()->getFilters() as $name => $filter) {
            if (null !== $parentAssociation && $name === $parentAssociation) {
                continue;
            }

            if ($this->isHiddenDisplayableFilter((string) $name)) {
                continue;
            }

            if ($filter->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filters that should not trigger the "Данные фильтрованы!" banner (e.g. default archive).
     */
    protected function isHiddenDisplayableFilter(string $name): bool
    {
        if (ArchivableExtension::FILTER_ARCHIVED !== $name) {
            return false;
        }

        $archivedValue = $this->getFilterParameters()[$name]['value'] ?? null;

        return null === $archivedValue || '' === $archivedValue || BooleanType::TYPE_NO === (int) $archivedValue;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_page'] = 1;
        $sortValues['_per_page'] = 40;

        if ($this instanceof HasSortable) {
            $sortValues['_sort_by'] = 'position';
            $sortValues['_sort_order'] = 'ASC';
        } else {
            $sortValues['_sort_by'] = 'id';
            $sortValues['_sort_order'] = 'DESC';
        }
    }

    protected function configureFormOptions(array &$formOptions): void
    {
        $formOptions['validation_groups'] = [self::FORM_GROUP];
        $formOptions['translation_domain'] = $this->getTranslationDomain();
    }

    public function getPerPageOptions(): array
    {
        return [20, 40, 60, 120, 180];
    }

    public function isAutocompleteRequest(): bool
    {
        $request = $this->peekCurrentRequest();

        return null !== $request
            && 'sonata_admin_retrieve_autocomplete_items' === $request->attributes->get('_route');
    }

    /**
     * Current HTTP request without attaching it to this admin.
     *
     * Association admins (autocomplete target) do not match `_sonata_admin`
     * and must not get setRequest() during PathInfoBuilder.
     */
    protected function peekCurrentRequest(): ?Request
    {
        if ($this->hasRequest()) {
            return $this->getRequest();
        }

        if (null === $this->container || !$this->container->has('request_stack')) {
            return null;
        }

        $request = $this->container->get('request_stack')->getCurrentRequest();

        return $request instanceof Request ? $request : null;
    }

    /**
     * Sonata 4 getRequest() throws if AdminFetcher did not call setRequest
     * (association admin, model-manager hooks). Fall back to the current HTTP request
     * only for the admin that matches `_sonata_admin`.
     *
     * Do not attach the request when `_sonata_admin` is missing: during sonata_admin
     * route loading PathInfoBuilder calls getClass(), and a foreign `?subclass=`
     * would break other admins that have their own subclasses.
     */
    protected function getHttpRequest(): ?Request
    {
        $this->ensureHttpRequest();

        return $this->hasRequest() ? $this->getRequest() : null;
    }

    protected function getRequestValue(string $key, mixed $default = null): mixed
    {
        $request = $this->getHttpRequest();
        if (null === $request) {
            return $default;
        }

        return RequestBag::get($request, $key, $default);
    }

    private function ensureHttpRequest(): void
    {
        if ($this->hasRequest() || null === $this->container || !$this->container->has('request_stack')) {
            return;
        }

        $request = $this->container->get('request_stack')->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $routeAdminCode = $request->attributes->get('_sonata_admin');
        if (!\is_string($routeAdminCode)
            || $this->getRoot()->getCode() !== $this->getRouteAdminRootCode($routeAdminCode)) {
            return;
        }

        $this->setRequest($request);
    }

    private function getRouteAdminRootCode(string $routeAdminCode): string
    {
        if (str_contains($routeAdminCode, '|')) {
            return explode('|', $routeAdminCode, 2)[0];
        }

        if (str_contains($routeAdminCode, '.')) {
            return explode('.', $routeAdminCode, 2)[0];
        }

        return $routeAdminCode;
    }

    protected function configureBatchActions(array $actions): array
    {
        if (!$this instanceof WithDeleteActionInList && isset($actions['delete'])) {
            unset($actions['delete']);
        }

        return $actions;
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        parent::configureRoutes($collection);

        if ($this instanceof HasSortable) {
            $collection->add('move', $this->getRouterIdParameter().'/move/{position}');
        }

        if ($this->hasAdvancedFilters()) {
            $collection->add('advanced_filter');
        }

        if (is_subclass_of($this->getModelClass(), Archivable::class)) {
            $collection->add('archive', $this->getRouterIdParameter().'/archive');
            $collection->add('unarchive', $this->getRouterIdParameter().'/unarchive');
        }
    }

    protected function configureActionButtons(array $buttonList, string $action, ?object $object = null): array
    {
        $list = parent::configureActionButtons($buttonList, $action, $object);

        if (isset($list['list'])) {
            unset($list['list']);
        }

        if (isset($list['create'])) {
            $createButton = $list['create'];
            unset($list['create']);

            $list['create'] = $createButton;
        }

        if ('list' === $action && $this->hasAdvancedFilters()) {
            $list['advanced_filter'] = [
                'template' => '@GlavwebAdmin/Filter/advanced_filter_button.html.twig',
            ];
        }

        if (
            $object
            && \in_array($action, ['show', 'edit'], true)
            && $this->hasRoute('delete')
            && $this->canDelete($object)
            && $this->canAccessObjectAction('delete', $object)
        ) {
            $list['delete'] = [
                'template' => '@SonataAdmin/Button/delete_button.html.twig',
            ];
        }

        if ($object && \in_array($action, ['show', 'edit'], true)) {
            if ($this->hasAccess('archive', $object) && $this->hasRoute('archive') && $this->canArchive($object)) {
                $list['archive'] = [
                    'template' => '@GlavwebAdmin/CRUD/action/icon_action_button.html.twig',
                    'options' => [
                        'icon_class' => 'fa-archive',
                        'title' => $this->trans('archive.action_archive', [], 'GlavwebAdminBundle'),
                        'url' => $this->generateObjectUrl('archive', $object),
                    ],
                ];
            }
            if ($this->hasAccess('unarchive', $object) && $this->hasRoute('unarchive') && $this->isArchived($object)) {
                $list['unarchive'] = [
                    'template' => '@GlavwebAdmin/CRUD/action/icon_action_button.html.twig',
                    'options' => [
                        'icon_class' => 'fa-undo',
                        'title' => $this->trans('archive.action_unarchive', [], 'GlavwebAdminBundle'),
                        'url' => $this->generateObjectUrl('unarchive', $object),
                    ],
                ];
            }
        }

        if ($object && !$this->canAccessObjectAction('edit', $object)) {
            unset($list['edit']);
        }

        return $list;
    }

    public function getExportFormats(): array
    {
        return ['xlsx', 'xls', 'csv', 'json'];
    }

    /**
     * Example: getHelpForImage($task, 'imageFile').
     */
    protected function getHelpForImage(object $entity, string $fieldName): string
    {
        $imageHelp = '';

        $property = null;
        $reflectionClass = new ReflectionClass($entity);
        while (true) {
            if ($reflectionClass->hasProperty($fieldName)) {
                $property = $reflectionClass->getProperty($fieldName);
                break;
            }

            if ($reflectionClass->getParentClass()) {
                $reflectionClass = $reflectionClass->getParentClass();
            } else {
                throw new RuntimeException(sprintf('Property name %s not found', $fieldName));
            }
        }

        $attributes = $property->getAttributes(Image::class);
        $annotation = [] !== $attributes ? $attributes[0]->newInstance() : null;
        if ($annotation instanceof Image
            && ($annotation->minWidth && $annotation->minHeight || $annotation->maxWidth && $annotation->maxHeight)
        ) {
            $domain = 'GlavwebAdminBundle';
            $imageHelp = $this->trans('image_help.allowed_size', [], $domain);

            if ($annotation->minWidth == $annotation->maxWidth && $annotation->minHeight == $annotation->maxHeight) {
                $imageHelp .= ' '.$this->trans('image_help.exact', ['%w%' => $annotation->minWidth, '%h%' => $annotation->minHeight], $domain);
            } else {
                if ($annotation->minWidth && $annotation->minHeight) {
                    $imageHelp .= ' '.$this->trans('image_help.from', ['%w%' => $annotation->minWidth, '%h%' => $annotation->minHeight], $domain);
                }

                if ($annotation->maxWidth && $annotation->maxHeight) {
                    $imageHelp .= ' '.$this->trans('image_help.to', ['%w%' => $annotation->maxWidth, '%h%' => $annotation->maxHeight], $domain);
                }
            }
        }

        return $imageHelp;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function getContainer(): ContainerInterface
    {
        if (null === $this->container) {
            throw new LogicException(sprintf('Container was not set on admin "%s".', static::class));
        }

        return $this->container;
    }

    public function getParameter(string $name): mixed
    {
        if (!$this->getContainer()->has('parameter_bag')) {
            throw new LogicException(sprintf('parameter_bag is not available on admin "%s".', static::class));
        }

        return $this->getContainer()->get('parameter_bag')->get($name);
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->getTranslator()->trans(
            $id,
            $parameters,
            $domain ?? $this->getTranslationDomain(),
            $locale
        );
    }

    public function getDoctrine(): ManagerRegistry
    {
        return $this->getContainer()->get('doctrine');
    }

    /**
     * @param class-string $class
     */
    public function getRepository(string $class): ObjectRepository
    {
        return $this->getDoctrine()->getRepository($class);
    }

    public function getAuthorizationChecker(): AuthorizationChecker
    {
        return $this->getContainer()->get('security.authorization_checker');
    }

    /**
     * @throws LogicException If SecurityBundle is not available
     */
    public function getUser(): ?UserInterface
    {
        $container = $this->getContainer();

        if (!$container->has('security.token_storage')) {
            throw new LogicException('The SecurityBundle is not registered in your application.');
        }

        /** @var TokenInterface|null $token */
        $token = $container->get('security.token_storage')->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    protected function generateFilterPath(array $filters, array $queryParameters = []): string
    {
        $request = $this->getRequest();
        $routeName = $request->attributes->get('_route');
        $queryParams = array_merge($request->query->all(), $queryParameters);
        $routeParameters = array_merge($request->attributes->get('_route_params'), $queryParams);

        foreach ($filters as $filterKey => $filterData) {
            if (!isset($filterData['value'])) {
                continue;
            }

            $filterValue = $filterData['value'];
            $filterIsArray = $filterData['is_array'] ?? false;
            $filterMerge = $filterData['merge'] ?? false;

            $value = $this->normalizeFilterMenuValue($filterValue);
            if ($filterIsArray) {
                $value = (array) $value;

                if ($filterMerge) {
                    $isExists =
                        isset($routeParameters['filter'][$filterKey]['value'])
                        && is_array($routeParameters['filter'][$filterKey]['value']);

                    if ($isExists) {
                        $value = array_unique(array_merge($routeParameters['filter'][$filterKey]['value'], $value));
                    }
                }
            }

            $routeParameters['filter'][$filterKey] = [
                'type' => '',
                'value' => $value,
            ];
        }

        return $this->getRouteGenerator()->generate($routeName, $routeParameters);
    }

    /**
     * Router/http_build_query сериализует BackedEnum как {name, value} —
     * Sonata ChoiceFilter такой query не применяет.
     * null из query выпадает, и дефолт фильтра (например «Последний месяц»)
     * снова подставляется через ParametersManipulator::merge.
     */
    private function normalizeFilterMenuValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (null === $value) {
            return '';
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeFilterMenuValue($item);
            }
        }

        return $value;
    }

    public function generateUrlWithParams(array $parameters = []): string
    {
        $request = $this->getRequest();

        $routeName = $request->attributes->get('_route');
        $routeParams = $request->attributes->get('_route_params');
        $queryParams = array_merge($routeParams, $request->query->all(), $parameters);

        return $this->getRouteGenerator()->generate($routeName, $queryParams);
    }

    protected function filterMenuOptions(array $filterParameters, array $queryParameters = []): array
    {
        return [
            'current' => $this->compareFilterParameters($filterParameters, $queryParameters),
            'uri' => $this->generateFilterPath($filterParameters, $queryParameters),
        ];
    }

    protected function compareFilterParameters(array $filterParameters, array $queryParameters = []): bool
    {
        if ($queryParameters) {
            $request = $this->getRequest();
            $requestQueryParameters = $request->query->all();

            foreach ($queryParameters as $key => $value) {
                if ('' == $value) {
                    $current = !isset($requestQueryParameters[$key]) || '' == $requestQueryParameters[$key];
                } else {
                    $current = isset($requestQueryParameters[$key]) && $requestQueryParameters[$key] == $value;
                }

                if (!$current) {
                    return false;
                }
            }

            return true;
        }

        $current = false;
        $filters = $this->getDatagrid()->getFilters();
        $currentFilters = $this->getFilterParameters();

        foreach ($filterParameters as $key => $filterParameter) {
            $filterName = str_replace('__', '.', $key);
            $formName = str_replace('.', '__', $filterName);

            if (!isset($filters[$filterName])) {
                return empty($filterParameter['value'] ?? null);
            }

            $filterValue = $currentFilters[$formName]
                ?? $currentFilters[$key]
                ?? $currentFilters[$filterName]
                ?? null;

            if (array_key_exists('is_array', $filterParameter)) {
                unset($filterParameter['is_array']);
            }

            if (array_key_exists('merge', $filterParameter)) {
                unset($filterParameter['merge']);
            }

            if (isset($filterParameter['value'])) {
                $filterParameter['value'] = $this->normalizeFilterMenuValue($filterParameter['value']);
            }

            if (!isset($filterParameter['type']) && \is_array($filterValue)
                && \array_key_exists('type', $filterValue) && null === $filterValue['type']) {
                unset($filterValue['type']);
            }

            if (!\array_key_exists('type', $filterParameter) && \is_array($filterValue)) {
                unset($filterValue['type']);
            }

            if (isset($filterParameter['value']) && \is_array($filterParameter['value'])
                && \array_key_exists('start', $filterParameter['value'])
                && \array_key_exists('end', $filterParameter['value'])
                && empty($filterParameter['value']['start']) && empty($filterParameter['value']['end'])
            ) {
                $filterParameter['value'] = '';
            }

            if (isset($filterValue['value']) && \is_array($filterValue['value'])
                && \array_key_exists('start', $filterValue['value'])
                && \array_key_exists('end', $filterValue['value'])
                && empty($filterValue['value']['start']) && empty($filterValue['value']['end'])
            ) {
                $filterValue['value'] = '';
            }

            if (isset($filterValue['value']) && \is_object($filterValue['value']) && method_exists($filterValue['value'], 'getId')) {
                $filterValue['value'] = $filterValue['value']->getId();
            }

            if (isset($filterValue['value']) && \is_scalar($filterValue['value'])) {
                $filterValue['value'] = (string) $filterValue['value'];
            }

            if (isset($filterParameter['value']) && \is_scalar($filterParameter['value'])) {
                $filterParameter['value'] = (string) $filterParameter['value'];
            }

            if (null === $filterValue) {
                $filterValue = ['value' => ''];
            }

            if (!isset($filterParameter['value']) || null === $filterParameter['value'] || [] === $filterParameter['value']) {
                $filterParameter['value'] = '';
            }

            if ($filterParameter) {
                array_multisort($filterParameter);
            }

            if ($filterValue) {
                array_multisort($filterValue);
            }

            $current = serialize($filterParameter) === serialize($filterValue);

            if (!$current) {
                return false;
            }
        }

        return $current;
    }

    protected function hasBundle(string $bundleName): bool
    {
        $bundles = $this->getContainer()->get('kernel')->getBundles();

        return isset($bundles[$bundleName]);
    }

    protected function disableSqlFilter(string $filterName): void
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        if ($em->getFilters()->isEnabled($filterName)) {
            $em->getFilters()->disable($filterName);
        }
    }

    protected function enableSqlFilter(string $filterName): void
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        if (!$em->getFilters()->isEnabled($filterName)) {
            $em->getFilters()->enable($filterName);
        }
    }

    protected function getCommandBus(): object
    {
        return $this->getContainer()->get('tactician.commandbus.default');
    }

    protected function addErrorToForm(string $message, ?FormInterface $property = null): void
    {
        if (!$property) {
            $property = $this->getForm();
        }

        $property->addError(new FormError($message));
    }

    protected function createModelManagerExceptionFrom(Exception $e): ModelManagerException
    {
        $message = $e->getMessage();

        if ($e instanceof InvalidEntityException && $e->getViolations()->count() > 0) {
            foreach ($e->getViolations() as $violation) {
                if (empty($violation->getPropertyPath())) {
                    $message = (string) $violation->getMessage();
                    break;
                }
            }
        }

        return new ModelManagerException($message, $e->getCode(), $e);
    }

    protected function addFlash(string $type, $message): void
    {
        $container = $this->getContainer();

        if (!$container->has('request_stack')) {
            throw new LogicException('You can not use the addFlash method if request_stack is not available.');
        }

        try {
            $session = $container->get('request_stack')->getSession();
        } catch (SessionNotFoundException $e) {
            throw new LogicException('You can not use the addFlash method if sessions are disabled. Enable them in "config/packages/framework.yaml".', 0, $e);
        }

        if (!$session instanceof FlashBagAwareSessionInterface) {
            throw new LogicException(\sprintf('You can not use the addFlash method because class "%s" does not implement "%s".', \get_debug_type($session), FlashBagAwareSessionInterface::class));
        }

        $session->getFlashBag()->add($type, $message);
    }

    protected function addFlashError(string $message): void
    {
        $this->addFlash('sonata_flash_error', $message);
    }

    public function getAdminLink(
        string $name,
        string $route,
        array $routeParameters = [],
        int $referenceType = Router::ABSOLUTE_PATH,
        bool $targetBlank = false,
    ): string {
        $router = $this->getContainer()->get('router');

        return '<a'.($targetBlank ? ' target="_blank"' : '').' href="'.$router->generate($route, $routeParameters, $referenceType).'">'.$name.'</a>';
    }

    public function isGrantedByAdminId(string $adminId, $attributes, ?int $objectId = null): bool
    {
        $admin = $this->getConfigurationPool()->getInstance($adminId);

        $object = null;
        if ($objectId) {
            $object = $admin->getObject($objectId);
        }

        return $admin->isGranted($attributes, $object);
    }

    public function getAdminPageLink(
        string $name,
        string $adminId,
        string $page,
        ?int $objectId = null,
        bool $targetBlank = false,
    ): string {
        $attributes = strtoupper($page);
        if ('SHOW' === $attributes) {
            $attributes = 'VIEW';
        }

        $isGranted = $this->isGrantedByAdminId($adminId, $attributes, $objectId);

        if (!$isGranted) {
            $targetAdmin = $this->getConfigurationPool()->getInstance($adminId);

            if ('edit' == $page && $targetAdmin->hasRoute('show')) {
                return $this->getAdminPageLink($name, $adminId, 'show', $objectId, $targetBlank);
            }

            return $name;
        }

        if ('edit' == $page || 'show' == $page) {
            if (!$objectId) {
                throw new RuntimeException('For page "'.$page.'" you need set objectId parameter.');
            }

            return $this->getAdminLink($name, $adminId.'_'.$page, ['id' => $objectId], Router::ABSOLUTE_PATH, $targetBlank);
        }

        if ('list' == $page) {
            return $this->getAdminLink($name, $adminId.'_'.$page, [], Router::ABSOLUTE_PATH, $targetBlank);
        }

        return '';
    }

    /**
     * Sonata 4 getParent() throws if the admin is not nested. Use this for list/root checks.
     */
    public function getParentOrNull(): ?AdminInterface
    {
        return $this->isChild() ? $this->getParent() : null;
    }

    /**
     * Sonata 4 getParentFieldDescription() throws if none (embedded form admins).
     */
    public function getParentFieldDescriptionOrNull(): ?FieldDescriptionInterface
    {
        return $this->hasParentFieldDescription() ? $this->getParentFieldDescription() : null;
    }

    public function getParentSubject(): mixed
    {
        $parent = $this->getParentOrNull();
        if (!$parent instanceof AdminInterface || !$parent->hasSubject()) {
            return null;
        }

        return $parent->getSubject();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStaticTextFormType(string $data, array $formOptions = []): array
    {
        return array_merge([
            'required' => false,
            'mapped' => false,
            'label' => false,
            'data' => $data,
        ], $formOptions);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStaticTemplateFormType(string $template, array $context = [], array $formOptions = []): array
    {
        $data = $this->getContainer()->get('twig')->render($template, $context);

        return array_merge([
            'required' => false,
            'mapped' => false,
            'label' => false,
            'data' => $data,
        ], $formOptions);
    }

    public function hasFilter(string $name): bool
    {
        $filterParameters = $this->getFilterParameters();

        return isset($filterParameters[$name]) && '' !== $filterParameters[$name]['value'];
    }

    protected function flush(): void
    {
        $this->getDoctrine()->getManager()->flush();
    }

    /**
     * @return list<string>
     */
    protected function getExcludedAdminServiceIdPrefixes(): array
    {
        return [];
    }

    public function getAdminByClass($class)
    {
        $pool = $this->getConfigurationPool();
        if (!$pool->hasAdminByClass($class)) {
            throw new LogicException(sprintf('Pool has no admin for the class %s.', $class));
        }

        $var = $pool->getAdminClasses()[$class];
        $excludedPrefixes = $this->getExcludedAdminServiceIdPrefixes();
        $adminClasses = array_filter($var, static function ($adminClass) use ($excludedPrefixes): bool {
            foreach ($excludedPrefixes as $prefix) {
                if (str_starts_with($adminClass, $prefix)) {
                    return false;
                }
            }

            return true;
        });

        if (\count($adminClasses) > 1) {
            throw new RuntimeException(sprintf('Unable to find a valid admin for the class: %s, there are too many registered: %s', $class, implode(', ', $adminClasses)));
        }

        return $pool->getInstance($adminClasses[0]);
    }

    public function canArchive(object $object): bool
    {
        /** @var ArchiveManager $archiveManager */
        $archiveManager = $this->getContainer()->get(ArchiveManager::class);

        return $archiveManager->canArchive($object);
    }

    public function isArchived(object $object): bool
    {
        /** @var ArchiveManager $archiveManager */
        $archiveManager = $this->getContainer()->get(ArchiveManager::class);

        return $archiveManager->isArchived($object);
    }

    public function canDelete(object $object): bool
    {
        /** @var DeletionManager $deletionManager */
        $deletionManager = $this->getContainer()->get(DeletionManager::class);

        return $deletionManager->canDelete($object);
    }

    public function canAccessObjectAction(string $action, object $object): bool
    {
        return true;
    }

    /**
     * List identifier URL: show if available, otherwise edit.
     */
    public function getListObjectUrl(object $object): ?string
    {
        $this->ensureChildParentRequestAttributesFromObject($object);

        try {
            if ($this->hasRoute('show') && $this->hasAccess('show', $object) && $this->canAccessObjectAction('show', $object)) {
                return $this->generateObjectUrl('show', $object);
            }

            if ($this->hasRoute('edit') && $this->hasAccess('edit', $object) && $this->canAccessObjectAction('edit', $object)) {
                return $this->generateObjectUrl('edit', $object);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Fills parent admin ids in request attributes for child admins (x-editable / list links).
     */
    private function ensureChildParentRequestAttributesFromObject(object $object): void
    {
        if (!$this->isChild() || !$this->hasRequest()) {
            return;
        }

        $request = $this->getRequest();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $currentAdmin = $this;
        $currentObject = $object;

        while ($currentAdmin->isChild()) {
            $parentAdmin = $currentAdmin->getParent();
            $parentIdParameter = $parentAdmin->getIdParameter();
            $mapping = $currentAdmin->getParentAssociationMapping();

            $parentObject = null;
            if (null !== $mapping) {
                try {
                    $value = $propertyAccessor->getValue($currentObject, $mapping);
                    if (\is_object($value)) {
                        $parentObject = $value;
                    }
                } catch (Throwable) {
                    $parentObject = null;
                }
            }

            if (
                null !== $parentObject
                && (
                    !$request->attributes->has($parentIdParameter)
                    || '' === (string) $request->attributes->get($parentIdParameter)
                )
            ) {
                $request->attributes->set(
                    $parentIdParameter,
                    $parentAdmin->getUrlSafeIdentifier($parentObject)
                );
            }

            if (null === $parentObject) {
                break;
            }

            $currentObject = $parentObject;
            $currentAdmin = $parentAdmin;
        }
    }
}
