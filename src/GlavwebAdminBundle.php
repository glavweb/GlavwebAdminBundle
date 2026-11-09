<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle;

use Glavweb\AdminBundle\DependencyInjection\Compiler\AdminDelegatingModelManagerPass;
use Glavweb\AdminBundle\DependencyInjection\Compiler\TwigSonataAdminOverridesPass;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Class GlavwebAdminBundle.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class GlavwebAdminBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TwigSonataAdminOverridesPass());
        $container->addCompilerPass(new AdminDelegatingModelManagerPass());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/packages/*.yaml');
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('glavweb_admin.maker.admin_services_file', $config['maker']['admin_services_file']);
        $builder->setParameter('glavweb_admin.maker.entity_namespace', $config['maker']['entity_namespace']);
        $builder->setParameter('glavweb_admin.sortable.position_field', $config['sortable']['position_field']);
        $builder->setParameter('glavweb_admin.sortable.groups', $config['sortable']['groups']);
        $builder->setParameter('glavweb_admin.admin_locator_services', $config['admin_locator_services']);
        $builder->setParameter('glavweb_admin.delete_in_list', $config['delete_in_list']);

        $container->import('../config/services.yaml');

        if (class_exists(AbstractMaker::class)) {
            $container->import('../config/services_maker.yaml');
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('maker')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('entity_namespace')->defaultValue('App\\Entity\\')->end()
                        ->scalarNode('admin_services_file')->defaultValue('config/admin.yaml')->end()
                    ->end()
                ->end()
                ->arrayNode('sortable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('position_field')->defaultValue(['default' => 'position', 'entities' => []])->end()
                        ->variableNode('groups')->defaultValue(['entities' => []])->end()
                    ->end()
                ->end()
                ->arrayNode('admin_locator_services')
                    ->info('Extra service ids exposed to AbstractAdmin::getContainer() (app helpers, managers, …).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->enumNode('delete_in_list')
                    ->info('deny: delete in list/batch only for admins implementing WithDeleteActionInList. allow: no restriction.')
                    ->values(['deny', 'allow'])
                    ->defaultValue('deny')
                ->end()
            ->end()
        ;
    }
}
