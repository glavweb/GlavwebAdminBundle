<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Extension;

use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Adds `dialog_layout` to Sonata ModelAutocompleteType: layout of the btn_add field dialog.
 *
 *   ->add('tags', ModelAutocompleteType::class, [
 *       'btn_add' => '…',
 *       'dialog_layout' => 'wide', // compact (default) | wide | drawer
 *   ])
 *
 * Rendered by @GlavwebAdmin/SonataAdmin/CRUD/Association/edit_modal.html.twig.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
final class ModelAutocompleteTypeExtension extends AbstractTypeExtension
{
    public const string DIALOG_LAYOUT_COMPACT = 'compact';
    public const string DIALOG_LAYOUT_WIDE = 'wide';
    public const string DIALOG_LAYOUT_DRAWER = 'drawer';

    public const array DIALOG_LAYOUTS = [
        self::DIALOG_LAYOUT_COMPACT,
        self::DIALOG_LAYOUT_WIDE,
        self::DIALOG_LAYOUT_DRAWER,
    ];

    public static function getExtendedTypes(): iterable
    {
        return [ModelAutocompleteType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('dialog_layout', self::DIALOG_LAYOUT_COMPACT);
        $resolver->setAllowedValues('dialog_layout', self::DIALOG_LAYOUTS);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['dialog_layout'] = $options['dialog_layout'];
    }
}
