<?php declare(strict_types=1);

namespace Shopwell\Core\Profiling\Compiler;

use Composer\InstalledVersions;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Profiling\Controller\ProfilerController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class RemoveDevServices implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!InstalledVersions::isInstalled('symfony/web-profiler-bundle') || !$container->hasDefinition('profiler')) {
            $container->removeDefinition(ProfilerController::class);
        }
    }
}
