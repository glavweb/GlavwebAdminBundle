<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class FormStaticControlRawType.
 */
class FormStaticControlRawType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'disabled' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'bs_static_raw';
    }

    /**
     * Compatibility with Symfony 2.
     */
    public function getName(): string
    {
        return 'bs_static_raw';
    }
}
