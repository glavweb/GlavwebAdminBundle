<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\Action;

use RuntimeException;
use Glavweb\ExtraBundle\Http\RequestBag;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Filter\FilterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class RetrieveAutocompleteItemsAction
{
    private Pool $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * Retrieve list of items for autocomplete form field.
     *
     * @throws RuntimeException
     * @throws AccessDeniedException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $admin = $this->pool->getInstance(RequestBag::get($request, 'admin_code'));
        $admin->setRequest($request);
        $context = RequestBag::get($request, '_context', '');

        if ('filter' === $context) {
            $admin->checkAccess('list');
        } elseif (!$admin->hasAccess('create') && !$admin->hasAccess('edit')) {
            throw new AccessDeniedException();
        }

        // subject will be empty to avoid unnecessary database requests and keep autocomplete function fast
        $admin->setSubject($admin->getNewInstance());

        if ('filter' === $context) {
            // filter
            $fieldDescription = $this->retrieveFilterFieldDescription($admin, RequestBag::get($request, 'field'));
            $filterAutocomplete = $admin->getDatagrid()->getFilter($fieldDescription->getName());

            $property = $filterAutocomplete->getFieldOption('property');
            $callback = $filterAutocomplete->getFieldOption('callback');
            $minimumInputLength = $filterAutocomplete->getFieldOption('minimum_input_length', 0);
            $itemsPerPage = $filterAutocomplete->getFieldOption('items_per_page', 10);
            $reqParamPageNumber = $filterAutocomplete->getFieldOption('req_param_name_page_number', '_page');
            $toStringCallback = $filterAutocomplete->getFieldOption('to_string_callback');
            $targetAdminAccessAction = $filterAutocomplete->getFieldOption('target_admin_access_action', 'autocomplete');
            $responseItemCallback = $filterAutocomplete->getFieldOption('response_item_callback');
        } else {
            // create/edit form
            $fieldDescription = $this->retrieveFormFieldDescription($admin, RequestBag::get($request, 'field'));
            $formAutocomplete = $admin->getForm()->get($fieldDescription->getName());

            $formAutocompleteConfig = $formAutocomplete->getConfig();
            if ($formAutocompleteConfig->getAttribute('disabled')) {
                throw new AccessDeniedException('Autocomplete list can`t be retrieved because the form element is disabled or read_only.');
            }

            $property = $formAutocompleteConfig->getAttribute('property');
            $callback = $formAutocompleteConfig->getAttribute('callback');
            $minimumInputLength = $formAutocompleteConfig->getAttribute('minimum_input_length');
            $itemsPerPage = $formAutocompleteConfig->getAttribute('items_per_page');
            $reqParamPageNumber = $formAutocompleteConfig->getAttribute('req_param_name_page_number');
            $toStringCallback = $formAutocompleteConfig->getAttribute('to_string_callback');
            $targetAdminAccessAction = $formAutocompleteConfig->getAttribute('target_admin_access_action');
            $responseItemCallback = $formAutocompleteConfig->getAttribute('response_item_callback');
        }

        $searchText = RequestBag::get($request, 'q', '');

        $targetAdmin = $fieldDescription->getAssociationAdmin();

        // check user permission
        $targetAdmin->checkAccess($targetAdminAccessAction);

        if (mb_strlen($searchText, 'UTF-8') < $minimumInputLength) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Too short search string.'], Response::HTTP_FORBIDDEN);
        }

        $targetAdmin->setFilterPersister(null);
        $datagrid = $targetAdmin->getDatagrid();

        if (null !== $callback) {
            if (!\is_callable($callback)) {
                throw new RuntimeException('Callback does not contain callable function.');
            }

            $callback($targetAdmin, $property, $searchText);
        } else {
            if (\is_array($property)) {
                // multiple properties
                foreach ($property as $prop) {
                    if (!$datagrid->hasFilter($prop)) {
                        throw new RuntimeException(sprintf('To retrieve autocomplete items, you should add filter "%s" to "%s" in configureDatagridFilters() method.', $prop, $targetAdmin::class));
                    }

                    $filter = $datagrid->getFilter($prop);
                    $filter->setCondition(FilterInterface::CONDITION_OR);

                    $datagrid->setValue($filter->getFormName(), null, $searchText);
                }
            } else {
                if (!$datagrid->hasFilter($property)) {
                    throw new RuntimeException(sprintf('To retrieve autocomplete items, you should add filter "%s" to "%s" in configureDatagridFilters() method.', $property, $targetAdmin::class));
                }

                $datagrid->setValue($datagrid->getFilter($property)->getFormName(), null, $searchText);
            }
        }

        $datagrid->setValue('_per_page', null, $itemsPerPage);
        $datagrid->setValue('_page', null, $request->query->get($reqParamPageNumber, '1'));
        $datagrid->buildPager();

        $pager = $datagrid->getPager();

        $items = [];
        $results = $pager->getCurrentPageResults();

        foreach ($results as $model) {
            if (\is_array($model)) {
                $model = $model[0] ?? null;
            }

            if (!\is_object($model)) {
                continue;
            }

            if (null !== $toStringCallback) {
                if (!\is_callable($toStringCallback)) {
                    throw new RuntimeException('Option "to_string_callback" does not contain callable function.');
                }

                $label = $toStringCallback($model, $property);
            } else {
                $resultMetadata = $targetAdmin->getObjectMetadata($model);
                $label = $resultMetadata->getTitle();
            }

            $item = [
                'id' => $targetAdmin->id($model),
                'label' => $label,
                'text' => $label,
            ];

            if (\is_callable($responseItemCallback)) {
                $item = \call_user_func($responseItemCallback, $admin, $model, $item);
            }

            $items[] = $item;
        }

        return new JsonResponse([
            'status' => 'OK',
            'more' => !$pager->isLastPage(),
            'items' => $items,
        ]);
    }

    /**
     * Retrieve the filter field description given by field name.
     *
     * @throws RuntimeException
     */
    private function retrieveFilterFieldDescription(
        AdminInterface $admin,
        string $field,
    ): FieldDescriptionInterface {
        $fieldDescription = $admin->getFilterFieldDescription($field);

        if (!$fieldDescription) {
            throw new RuntimeException(sprintf('The field "%s" does not exist.', $field));
        }

        if (null === $fieldDescription->getTargetModel()) {
            throw new RuntimeException(sprintf('No associated entity with field "%s".', $field));
        }

        return $fieldDescription;
    }

    /**
     * Retrieve the form field description given by field name.
     *
     * @throws RuntimeException
     */
    private function retrieveFormFieldDescription(
        AdminInterface $admin,
        string $field,
    ): FieldDescriptionInterface {
        $fieldDescription = $admin->getFormFieldDescription($field);

        if (!$fieldDescription) {
            throw new RuntimeException(sprintf('The field "%s" does not exist.', $field));
        }

        if (null === $fieldDescription->getTargetModel()) {
            throw new RuntimeException(sprintf('No associated entity with field "%s".', $field));
        }

        return $fieldDescription;
    }
}
