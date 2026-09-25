<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FeatureFlagCompilerPass::class)]
class FeatureFlagsCompilerPassTest extends TestCase
{
    private FeatureFlagCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new FeatureFlagCompilerPass();
    }

    public function testItRemovesServiceIfInactive(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('shopwell.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => false,
                'default' => true,
                'major' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertFalse($container->hasDefinition('feature_service'));
    }

    public function testItKeepServiceIfActive(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('shopwell.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => true,
                'default' => true,
                'major' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertTrue($container->hasDefinition('feature_service'));
    }

    public function testItRemovesInactiveFeatureFlaggedServiceFromTaggedServices(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);
        $definition->addTag('shopwell.app_lifecycle.persister');

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('shopwell.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => false,
                'default' => true,
                'major' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertSame([], $container->findTaggedServiceIds('shopwell.app_lifecycle.persister'));
    }

    public function testItKeepsActiveFeatureFlaggedServiceInTaggedServices(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);
        $definition->addTag('shopwell.app_lifecycle.persister');

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('shopwell.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => true,
                'default' => true,
                'major' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertArrayHasKey('feature_service', $container->findTaggedServiceIds('shopwell.app_lifecycle.persister'));
    }
}
