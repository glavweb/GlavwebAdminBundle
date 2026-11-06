<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Prepends Glavweb SonataAdmin Twig overrides so they win over vendor SonataAdminBundle,
 * while app templates/bundles/SonataAdminBundle still take precedence when present.
 */
final class TwigSonataAdminOverridesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')) {
            return;
        }

        $path = \dirname(__DIR__, 3).'/templates/SonataAdmin';
        if (!is_dir($path)) {
            return;
        }

        $container->getDefinition('twig.loader.native_filesystem')
            ->addMethodCall('prependPath', [$path, 'SonataAdmin']);
    }
}
