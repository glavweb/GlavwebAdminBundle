<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers templates/SonataAdmin as an override layer for the `@SonataAdmin` namespace.
 *
 * Resulting lookup order for `@SonataAdmin/...`:
 *   1. app `templates/bundles/SonataAdminBundle/` (project override — always wins)
 *   2. this bundle `templates/SonataAdmin/`
 *   3. vendor `sonata-project/admin-bundle/templates/`
 *
 * TwigBundle registers paths via `addPath` in the order above (minus ours); a plain `prependPath`
 * would put the bundle in front of the app override, so the call is inserted right before the
 * vendor path instead.
 *
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
final class TwigSonataAdminOverridesPass implements CompilerPassInterface
{
    private const string NAMESPACE = 'SonataAdmin';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')) {
            return;
        }

        $path = \dirname(__DIR__, 3).'/templates/SonataAdmin';
        if (!is_dir($path)) {
            return;
        }

        $loader = $container->getDefinition('twig.loader.native_filesystem');
        $calls = $loader->getMethodCalls();

        // TwigBundle marks the vendor views dir by also registering it under "!SonataAdmin".
        $vendorPath = null;
        foreach ($calls as [$method, $arguments]) {
            if ('addPath' === $method && ($arguments[1] ?? null) === '!'.self::NAMESPACE) {
                $vendorPath = $arguments[0];
                break;
            }
        }

        $ourCall = ['addPath', [$path, self::NAMESPACE]];

        if (null === $vendorPath) {
            $loader->addMethodCall(...$ourCall);

            return;
        }

        $result = [];
        $inserted = false;
        foreach ($calls as $call) {
            [$method, $arguments] = $call;
            if (!$inserted && 'addPath' === $method && ($arguments[1] ?? null) === self::NAMESPACE && $arguments[0] === $vendorPath) {
                $result[] = $ourCall;
                $inserted = true;
            }
            $result[] = $call;
        }

        if (!$inserted) {
            $result[] = $ourCall;
        }

        $loader->setMethodCalls($result);
    }
}
