<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class TwigEnvironmentCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $twigEnvironment = $container->findDefinition('twig');
        // Symfony service subscriber somehow doesn't work. Therefore, the service has to be public
        $twigEnvironment->setPublic(true);
        $twigEnvironment->setClass(TwigEnvironment::class);

        $twigEnvironment->addTag('kernel.reset', ['method' => 'reset']);

        // The twig extension directly compiles the config into the service, there is no other way to get it @see \Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension::load
        $twigOptions = $twigEnvironment->getArgument(1);
        \assert(\is_array($twigOptions));

        $configuredTwigCache = $twigOptions['cache'] ?? false;
        if (!\is_string($configuredTwigCache)) {
            $container->setParameter('twig.cache', $container->getParameter('kernel.cache_dir') . '/twig');

            return;
        }

        $container->setParameter('twig.cache', $configuredTwigCache);
    }
}
