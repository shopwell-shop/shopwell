<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Event\BusinessEventRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class BusinessEventRegisterCompilerPass implements CompilerPassInterface
{
    /**
     * @param class-string[] $classes
     */
    public function __construct(private readonly array $classes)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addClasses', [$this->classes]);
    }
}
