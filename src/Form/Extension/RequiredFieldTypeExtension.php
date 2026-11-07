<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Extension;

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
 * Enforces form `required => true` on the server.
 *
 * Admin has `html5_validate: false`, so the asterisk is only a label.
 */
class RequiredFieldTypeExtension extends AbstractTypeExtension
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
        }, -16);
    }

    private function enforceRequired(FormInterface $form): void
    {
        foreach ($form as $child) {
            $this->enforceRequired($child);
        }

        if ($form->isRoot() || $form->getConfig()->getCompound()) {
            return;
        }

        if (!$form->getConfig()->getOption('required') || $form->isDisabled()) {
            return;
        }

        if (\is_bool($form->getData()) || $this->shouldSkipType($form)) {
            return;
        }

        if ($form->getErrors()->count() > 0) {
            return;
        }

        $violations = $this->validator->validate($form->getData(), new NotBlank());
        foreach ($violations as $violation) {
            $form->addError(new FormError(
                $violation->getMessage(),
                $violation->getMessageTemplate(),
                $violation->getParameters()
            ));
        }
    }

    private function shouldSkipType(FormInterface $form): bool
    {
        $innerType = $form->getConfig()->getType()->getInnerType();
        $prefix = $innerType->getBlockPrefix();

        return $innerType instanceof CheckboxType
            || $innerType instanceof HiddenType
            || $innerType instanceof ButtonType
            || $innerType instanceof FileType
            || \in_array($prefix, ['checkbox', 'hidden', 'file', 'submit', 'button', 'reset'], true);
    }
}
