<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin\AdvancedFilter;

use Glavweb\AdminBundle\SonataAdmin\AbstractAdmin;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Action advanced_filter для Sonata CRUDController.
 */
trait AdvancedFilterActionTrait
{
    protected function renderAdvancedFilter(
        Request $request,
        AdvancedFilterLocator $advancedFilterLocator,
        FormFactoryInterface $formFactory,
        string $template = '@GlavwebAdmin/Filter/advanced_filter.html.twig',
    ): Response {
        /** @var AbstractAdmin $admin */
        $admin = $this->admin;
        $admin->checkAccess('list');

        if (!$admin->hasAdvancedFilters()) {
            throw $this->createNotFoundException();
        }

        $bag = $admin->getAdvancedFilterBag();
        $context = $admin->createAdvancedFilterContext();
        $currentFilters = $admin->getFilterParameters();
        $formData = $this->advancedFilterParamsToFormData($bag, $advancedFilterLocator, $currentFilters);
        $form = $this->createAdvancedFilterForm($formFactory, $bag, $advancedFilterLocator, $context, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $advancedParams = $this->advancedFilterFormDataToParams($bag, $advancedFilterLocator, $form->getData() ?? []);
            $standardFilters = $request->request->all('filter');
            if ([] === $standardFilters) {
                $standardFilters = $request->query->all('filter');
            }
            if ([] === $standardFilters) {
                $standardFilters = $currentFilters;
            }

            foreach ($bag->getNames() as $name) {
                unset($standardFilters[$name]);
            }

            $merged = array_replace($standardFilters, $advancedParams);

            return new RedirectResponse($admin->generateUrl('list', ['filter' => $merged]));
        }

        $datagrid = $admin->getDatagrid();
        $filterForm = $datagrid->getForm()->createView();

        return $this->renderWithExtraParams($template, [
            'action' => 'advanced_filter',
            'admin' => $admin,
            'form' => $form->createView(),
            'filter_form' => $filterForm,
            'advanced_filter_bag' => $bag,
            'advanced_filter_names' => $bag->getNames(),
        ]);
    }

    /**
     * @param array<string, mixed> $filterParameters
     *
     * @return array<string, mixed>
     */
    private function advancedFilterParamsToFormData(
        AdvancedFilterBag $bag,
        AdvancedFilterLocator $advancedFilterLocator,
        array $filterParameters,
    ): array {
        $data = [];

        foreach ($bag->getFields() as $field) {
            $value = $filterParameters[$field->name]['value'] ?? null;

            if ($field->isAdvancedFilter()) {
                $advancedFilter = $advancedFilterLocator->get($field->advancedFilterClass);
                if (!$advancedFilter->isActive($value)) {
                    continue;
                }
                $formData = $advancedFilter->toFormData($value);
                if (null !== $formData && false !== $formData && '' !== $formData) {
                    $data[$field->name] = $formData;
                }

                continue;
            }

            if (null !== $value && '' !== $value && [] !== $value) {
                $data[$field->name] = ['value' => $value];
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $formData
     *
     * @return array<string, array{value: mixed}>
     */
    private function advancedFilterFormDataToParams(
        AdvancedFilterBag $bag,
        AdvancedFilterLocator $advancedFilterLocator,
        array $formData,
    ): array {
        $params = [];

        foreach ($bag->getFields() as $field) {
            if (!\array_key_exists($field->name, $formData)) {
                continue;
            }

            $raw = $formData[$field->name];

            if ($field->isAdvancedFilter()) {
                $advancedFilter = $advancedFilterLocator->get($field->advancedFilterClass);
                $value = $advancedFilter->normalizeFormValue($raw);
                if (!$advancedFilter->isActive($value)) {
                    continue;
                }
                $params[$field->name] = ['value' => $value];

                continue;
            }

            $value = \is_array($raw) && \array_key_exists('value', $raw) ? $raw['value'] : $raw;
            if (null === $value || '' === $value || [] === $value) {
                continue;
            }
            $params[$field->name] = ['value' => $value];
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAdvancedFilterForm(
        FormFactoryInterface $formFactory,
        AdvancedFilterBag $bag,
        AdvancedFilterLocator $advancedFilterLocator,
        AdvancedFilterContext $context,
        array $data,
    ): FormInterface {
        $builder = $formFactory->createNamedBuilder('advanced_filter', FormType::class, $data, [
            'csrf_protection' => true,
            'method' => 'POST',
        ]);

        foreach ($bag->getFields() as $field) {
            if ($field->isAdvancedFilter()) {
                $child = $builder->create($field->name, FormType::class, [
                    'label' => false,
                    'required' => false,
                ]);
                $advancedFilterLocator->get($field->advancedFilterClass)->buildForm($child, $context);
                $builder->add($child);

                continue;
            }

            $fieldType = $this->resolveAdvancedFilterFormType($field);
            $fieldOptions = $field->filterOptions['field_options'] ?? [];
            $fieldOptions['required'] = $fieldOptions['required'] ?? false;
            if (!isset($fieldOptions['label']) && isset($field->filterOptions['label'])) {
                $fieldOptions['label'] = $field->filterOptions['label'];
            }

            $valueBuilder = $builder->create('value', $fieldType, $fieldOptions);
            $wrapper = $builder->create($field->name, FormType::class, [
                'label' => false,
                'required' => false,
            ]);
            $wrapper->add($valueBuilder);
            $builder->add($wrapper);
        }

        return $builder->getForm();
    }

    private function resolveAdvancedFilterFormType(AdvancedFilterField $field): string
    {
        foreach ([$field->filterOptions['field_type'] ?? null, $field->type] as $candidate) {
            if (\is_string($candidate) && is_a($candidate, FormTypeInterface::class, true)) {
                return $candidate;
            }
        }

        return TextType::class;
    }
}
