<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Force empty_data = '' for scalar text fields.
 *
 * FormType's default empty_data is a Closure, so TextType does not register its
 * null→'' view transformer (`'' === $options['empty_data']` is false). With
 * html5_validate: false, empty submit can reach typed setters as null → TypeError 500
 * before NotBlank / RequiredFieldTypeExtension run.
 *
 * @author nilov <nilov@glavweb.ru>
 */
final class TextEmptyDataTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [
            TextType::class,
            TextareaType::class,
            EmailType::class,
            PasswordType::class,
            UrlType::class,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('empty_data', '');
    }
}
