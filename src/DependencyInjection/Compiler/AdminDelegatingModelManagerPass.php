<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\DependencyInjection\Compiler;

use Glavweb\AdminBundle\SonataAdmin\ModelManager\DelegatingModelManager;
use Glavweb\AdminBundle\SonataAdmin\NativeDatagridBuilder;
use Glavweb\AdminBundle\SonataAdmin\NativeListBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Each sonata.admin instance gets its own DelegatingModelManager (bind in AbstractAdmin::configure).
 *
 * Sonata Admin services are not shared; MM must not be shared either, otherwise a second
 * Admin instance would rebind the singleton and createObject/getForm() would run with no subject.
 *
 * Also injects a service locator for AbstractAdmin helpers (SF8 private security services).
 * Extra app services: parameter glavweb_admin.admin_locator_services (list of service ids).
 */
final class AdminDelegatingModelManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('sonata.admin.manager.orm') && !$container->hasAlias('sonata.admin.manager.orm')) {
            return;
        }

        $services = [
            'doctrine' => new Reference('doctrine'),
            'security.token_storage' => new Reference('security.token_storage'),
            'security.authorization_checker' => new Reference('security.authorization_checker'),
            'security.helper' => new Reference('security.helper'),
            'kernel' => new Reference('kernel'),
            'router' => new Reference('router'),
            'request_stack' => new Reference('request_stack'),
            'form.factory' => new Reference('form.factory'),
            'twig' => new Reference('twig'),
            'tactician.commandbus.default' => new Reference('tactician.commandbus.default'),
            'parameter_bag' => new Reference('parameter_bag'),
        ];

        foreach ([
            NativeDatagridBuilder::class,
            NativeListBuilder::class,
            'Glavweb\UploaderBundle\Manager\UploaderManager',
            'Glavweb\UploaderBundle\Helper\MediaHelper',
            'Glavweb\ApiBundle\Imagine\FilterHelper',
        ] as $optional) {
            $this->addIfPresent($container, $services, $optional);
        }

        $extra = $container->hasParameter('glavweb_admin.admin_locator_services')
            ? (array) $container->getParameter('glavweb_admin.admin_locator_services')
            : [];

        foreach ($extra as $id) {
            $this->addIfPresent($container, $services, (string) $id);
        }

        $locatorRef = ServiceLocatorTagPass::register($container, $services);

        foreach ($container->findTaggedServiceIds('sonata.admin') as $id => $tags) {
            $mmId = $id.'.delegating_model_manager';
            $mm = new Definition(DelegatingModelManager::class);
            $mm->setArguments([new Reference('sonata.admin.manager.orm')]);
            $mm->setPublic(false);
            $mm->setShared(false);
            $container->setDefinition($mmId, $mm);

            $definition = $container->getDefinition($id);
            $definition->addMethodCall('setModelManager', [new Reference($mmId)]);
            $definition->addMethodCall('setContainer', [$locatorRef]);
        }
    }

    /**
     * @param array<string, Reference> $services
     */
    private function addIfPresent(ContainerBuilder $container, array &$services, string $id): void
    {
        if ($container->hasDefinition($id) || $container->hasAlias($id)) {
            $services[$id] = new Reference($id);
        }
    }
}
