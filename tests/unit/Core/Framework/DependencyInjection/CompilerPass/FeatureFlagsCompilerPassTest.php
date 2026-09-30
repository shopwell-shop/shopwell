<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopwell\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopwell\Core\Framework\Feature;
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

    public function testItRejectsFeatureFlagsParameterWithWrongType(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopwell.feature.flags', 'invalid');

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType('shopwell.feature.flags', 'array', 'string'));
        $this->compilerPass->process($container);
    }

    #[DataProvider('featureTagsRequiringFlag')]
    public function testItRejectsFeatureTagWithoutFlag(string $tag): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('feature_service', (new Definition())->addTag($tag));
        $container->setParameter('shopwell.feature.flags', []);

        $this->expectExceptionObject(DependencyInjectionException::featureTagMissingFlag('feature_service', $tag));
        $this->compilerPass->process($container);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function featureTagsRequiringFlag(): iterable
    {
        yield 'new service tag' => ['shopwell.feature'];
        yield 'deprecated service tag' => ['shopwell.inactiveFeature'];
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

    public function testItRemovesInactiveFeatureTaggedServiceWhenFlagIsActive(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

        $container = new ContainerBuilder();
        $container->setDefinition('deprecated_service', $definition);
        $container->setParameter('shopwell.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => true],
        ]);

        Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertFalse($container->hasDefinition('deprecated_service'));
    }

    public function testItKeepsInactiveFeatureTaggedServiceWhenFlagIsInactive(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

        $container = new ContainerBuilder();
        $container->setDefinition('deprecated_service', $definition);
        $container->setParameter('shopwell.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => false],
        ]);

        Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasDefinition('deprecated_service'));
    }

    public function testItKeepsInactiveFeatureTaggedServiceForPendingMajor(): void
    {
        $definition = new Definition();
        $definition->addTag('shopwell.inactiveFeature', ['flag' => 'v6.9.0.0']);

        $container = new ContainerBuilder();
        $container->setDefinition('deprecated_service', $definition);
        $container->setParameter('shopwell.feature.flags', []);

        Feature::fake([], fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasDefinition('deprecated_service'));
    }

    public function testItRemovesListedServiceAliasesWhenFlagIsActive(): void
    {
        $previousClassNames = [
            'Shopwell\Administration\Controller\NotificationController',
            'Shopwell\Administration\Notification\NotificationDefinition',
            'Shopwell\Core\Framework\Plugin\Util\AssetService',
            'Shopwell\Elasticsearch\Product\SearchConfigLoader',
        ];

        $container = new ContainerBuilder();
        foreach ($previousClassNames as $previousClassName) {
            $currentClassName = ClassAliasRegistry::ALIASES[$previousClassName];
            $container->setDefinition($currentClassName, new Definition());
            $container->setAlias($previousClassName, $currentClassName);
        }
        $unlistedAlias = 'Shopwell\Administration\Notification\NotificationCollection';
        $container->setDefinition(ClassAliasRegistry::ALIASES[$unlistedAlias], new Definition());
        $container->setAlias($unlistedAlias, ClassAliasRegistry::ALIASES[$unlistedAlias])
            ->setDeprecated('shopwell/core', '6.7.0.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('shopwell.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => true],
        ]);

        Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        foreach ($previousClassNames as $previousClassName) {
            static::assertFalse($container->hasAlias($previousClassName));
            static::assertTrue($container->hasDefinition(ClassAliasRegistry::ALIASES[$previousClassName]));
        }
        static::assertTrue($container->hasAlias($unlistedAlias));
    }

    public function testItKeepsMovedClassServiceAliasWhenFlagIsInactive(): void
    {
        $previousClassName = 'Shopwell\Administration\Controller\NotificationController';
        $currentClassName = ClassAliasRegistry::ALIASES[$previousClassName];

        $container = new ContainerBuilder();
        $container->setDefinition($currentClassName, new Definition());
        $container->setAlias($previousClassName, $currentClassName)
            ->setDeprecated('shopwell/core', '6.7.0.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('shopwell.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => false],
        ]);

        Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasAlias($previousClassName));
    }

    public function testItKeepsMovedClassServiceAliasForUnregisteredMajor(): void
    {
        $previousClassName = 'Shopwell\Administration\Controller\NotificationController';
        $currentClassName = ClassAliasRegistry::ALIASES[$previousClassName];

        $container = new ContainerBuilder();
        $container->setDefinition($currentClassName, new Definition());
        $container->setAlias($previousClassName, $currentClassName)
            ->setDeprecated('shopwell/core', '6.7.0.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('shopwell.feature.flags', []);

        Feature::fake([], fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasAlias($previousClassName));
    }

    public function testItRemovesDeprecatedProductStreamInterfaceAliasWhenFlagIsActive(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ProductStreamBuilder::class, new Definition());
        $container->setAlias(ProductStreamBuilderInterface::class, ProductStreamBuilder::class)
            ->setDeprecated('shopwell/core', '6.8.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('shopwell.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => true],
        ]);

        Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertFalse($container->hasAlias(ProductStreamBuilderInterface::class));
        static::assertTrue($container->hasDefinition(ProductStreamBuilder::class));
    }

    public function testItKeepsDeprecatedProductStreamInterfaceAliasWhenFlagIsInactive(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ProductStreamBuilder::class, new Definition());
        $container->setAlias(ProductStreamBuilderInterface::class, ProductStreamBuilder::class)
            ->setDeprecated('shopwell/core', '6.8.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('shopwell.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => false],
        ]);

        Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasAlias(ProductStreamBuilderInterface::class));
    }
}
