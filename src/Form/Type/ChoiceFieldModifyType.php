<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class FieldsAffectChoiceType.
 *
 * Дает возможность модифицировать другие поля в зависимости от выбранного значения
 *
 * Опции:
 *  map: object
 *    [value: string]: object            Ключ: значение при выборе которого применяется конфигурация
 *      [fieldPath: string]: object      Ключ: Путь к модифицируемому полю
 *         show: bool                    Показывать поле если true
 *         value: mixed                  Установить значение
 *         defaultValue: mixed           Установить значение только если текущее значение равно null
 *         label: string                 Установить текст лейбла
 *         required: bool                Сделать поле обязательным (на клиенте и на сервере) если true
 *
 *  default_fields_state: array          Структура аналогична элементу из 'map'. Если определено, значения объединяются
 *                                       со значениями из 'map' для каждого значения
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class ChoiceFieldModifyType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $map = [];
        foreach ($options['map'] as $value => $fieldsData) {
            if (is_iterable($fieldsData)) {
                foreach ($fieldsData as $fieldName => $data) {
                    $sanitizedFieldName = str_replace(['__', '.'], ['____', '__'], $fieldName);
                    $map[$value][$sanitizedFieldName] = $data;
                }
                if ($options['default_fields_state']) {
                    $map[$value] = array_merge($options['default_fields_state'], $map[$value] ?? []);
                }
            }
        }

        $view->vars['map'] = $map;
        $view->vars['default_fields_state'] = $options['default_fields_state'];

        $options['expanded'] = false;

        parent::buildView($view, $form, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'map' => [],
            'default_fields_state' => null,
        ]);

        $resolver->setAllowedTypes('map', 'array');
        $resolver->setAllowedTypes('default_fields_state', ['array', 'null']);
    }

    /**
     * @phpstan-return class-string<FormTypeInterface>
     */
    public function getParent(): ?string
    {
        return ChoiceType::class;
    }

    /**
     * NEXT_MAJOR: Remove when dropping Symfony <2.8 support.
     */
    public function getName(): string
    {
        return $this->getBlockPrefix();
    }

    public function getBlockPrefix(): string
    {
        return 'choice_field_modify';
    }
}
