<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Extension;

use BackedEnum;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * ChoiceType with BackedEnum entity data and scalar choices (LabeledEnum::getFormChoices()).
 */
class BackedEnumChoiceTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setNormalizer('choice_value', static function (Options $options, $choiceValue) {
            if (\is_callable($choiceValue)) {
                return static function ($choice) use ($choiceValue) {
                    if ($choice instanceof BackedEnum) {
                        return (string) $choice->value;
                    }

                    return $choiceValue($choice);
                };
            }

            if (!self::choicesAreScalar($options['choices'] ?? [])) {
                return $choiceValue;
            }

            return static function ($choice): string {
                if ($choice instanceof BackedEnum) {
                    return (string) $choice->value;
                }

                if (false === $choice) {
                    return '0';
                }

                if (null === $choice || \is_scalar($choice)) {
                    return (string) $choice;
                }

                return '';
            };
        });
    }

    private static function choicesAreScalar(array $choices): bool
    {
        if ([] === $choices) {
            return false;
        }

        foreach ($choices as $choice) {
            if (\is_array($choice)) {
                if (!self::choicesAreScalar($choice)) {
                    return false;
                }

                continue;
            }

            if (!\is_scalar($choice) && null !== $choice) {
                return false;
            }
        }

        return true;
    }
}
