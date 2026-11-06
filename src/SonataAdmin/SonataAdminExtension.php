<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\SonataAdmin;

use Glavweb\AdminBundle\Naming\NamingStrategy;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Class SonataAdminExtension.
 *
 * @author Sergey Zvyagintsev <nitron.ru@gmail.com>
 */
#[AutoconfigureTag(name: 'sonata.admin.extension', attributes: ['global' => true])]
class SonataAdminExtension extends AbstractAdminExtension
{
    public function configure(AdminInterface $admin): void
    {
        /** @var class-string $modelClass */
        $modelClass = $admin->getModelClass();
        $domain = NamingStrategy::entityClassToSnakeCase($modelClass);

        // setTranslationDomain из admin.yaml вызывается до initialize();
        // не затирать явный домен (OpenLessonAdmin → open_lesson при сущности Lesson).
        if ('messages' === $admin->getTranslationDomain()) {
            $admin->setTranslationDomain($domain);
        }
    }
}
