<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Form\Extension;

use Glavweb\FrameworkBundle\Model\DeletionManager;
use Sonata\AdminBundle\Form\Type\AdminType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class AdminTypeExtension.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
class AdminTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly DeletionManager $deletionManager,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$builder->has('_delete')) {
            return;
        }

        $data = $builder->getData();

        if (null !== $data && method_exists($data, 'getId') && null !== $data->getId() && !$this->deletionManager->canDelete($data)) {
            $builder->get('_delete')->setDisabled(true);
        }
    }
}
