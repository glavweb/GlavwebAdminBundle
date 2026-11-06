<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Extension;

use Glavweb\AdminBundle\Form\Type\ChoiceFieldModifyType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Enforces `required: true` from ChoiceFieldModifyType `map` on the server.
 *
 * Админка работает с `html5_validate: false`, поэтому обязательность поля
 * из опции `required` в map нужно проверять на сервере. Проверка выполняется
 * на POST_SUBMIT корневой формы (после submit всех детей), чтобы не зависеть
 * от порядка полей: для каждого choice_field_modify читается отправленное
 * значение, по нему из map/default_fields_state вычисляются требуемые поля,
 * и для пустых из них добавляется FormError (как в RequiredFieldTypeExtension).
 */
class ChoiceFieldModifyRequiredTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['compound']) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->isRoot()) {
                return;
            }

            $this->enforceRequired($form);
        });
    }

    private function enforceRequired(FormInterface $root): void
    {
        foreach ($root as $choiceForm) {
            $innerType = $choiceForm->getConfig()->getType()->getInnerType();

            if (!$innerType instanceof ChoiceFieldModifyType) {
                continue;
            }

            $requiredFields = $this->resolveRequiredFields(
                $choiceForm->getConfig()->getOption('map'),
                $choiceForm->getConfig()->getOption('default_fields_state'),
                $choiceForm->getViewData()
            );

            $this->enforceRequiredFields($root, $requiredFields);
        }
    }

    private function resolveRequiredFields(array $map, ?array $defaultFieldsState, mixed $submittedValue): array
    {
        $fieldsConfig = null;

        foreach ($map as $value => $fieldsData) {
            if (is_iterable($fieldsData) && (string) $submittedValue === (string) $value) {
                $fieldsConfig = is_array($fieldsData) ? $fieldsData : iterator_to_array($fieldsData);
                break;
            }
        }

        if (null !== $defaultFieldsState) {
            $fieldsConfig = array_merge($defaultFieldsState, $fieldsConfig ?? []);
        }

        $requiredFields = [];

        foreach ($fieldsConfig ?? [] as $fieldName => $data) {
            if (!empty($data['required'])) {
                $requiredFields[] = $fieldName;
            }
        }

        return $requiredFields;
    }

    private function enforceRequiredFields(FormInterface $parent, array $requiredFields): void
    {
        foreach ($requiredFields as $fieldName) {
            if (!$parent->has($fieldName)) {
                continue;
            }

            $field = $parent->get($fieldName);

            if ($field->isDisabled() || $field->getErrors()->count() > 0 || $this->shouldSkipType($field)) {
                continue;
            }

            if (\is_bool($field->getData())) {
                continue;
            }

            $violations = $this->validator->validate($field->getData(), new NotBlank());

            foreach ($violations as $violation) {
                $field->addError(new FormError(
                    (string) $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getParameters()
                ));
            }
        }
    }

    private function shouldSkipType(FormInterface $form): bool
    {
        $innerType = $form->getConfig()->getType()->getInnerType();

        return $innerType instanceof CheckboxType
            || $innerType instanceof HiddenType
            || $innerType instanceof ButtonType
            || $innerType instanceof FileType;
    }
}
