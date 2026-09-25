<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopwell\Core\Framework\Api\Cors\CorsHeaders;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEntityInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AutoconfigureCompilerPass::class)]
class AutoconfigureCompilerPassTest extends TestCase
{
    public function testAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('product', (new Definition(ProductDefinition::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->hasDefinition('product'));
        static::assertTrue($container->getDefinition('product')->hasTag('shopwell.entity.definition'));
    }

    public function testHookableEntityAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('hookable_entity', (new Definition(ExampleHookableEntity::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->hasDefinition('hookable_entity'));
        static::assertTrue($container->getDefinition('hookable_entity')->hasTag('shopwell.entity.hookable'));
    }

    public function testAliasing(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $definition = new Definition(ExampleService::class);
        $definition->setPublic(true);
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);

        $container->setDefinition('shopwell.filesystem.private', (new Definition(FilesystemOperator::class))->setPublic(true));
        $container->setDefinition('shopwell.filesystem.public', (new Definition(FilesystemOperator::class))->setPublic(true));

        $container->setDefinition('service', $definition);

        $container->compile(true);

        static::assertTrue($container->hasDefinition('service'));

        $arg1 = $definition->getArgument(0);
        static::assertInstanceOf(Reference::class, $arg1);
        static::assertSame('shopwell.filesystem.private', (string) $arg1);

        $arg2 = $definition->getArgument(1);
        static::assertInstanceOf(Reference::class, $arg2);
        static::assertSame('shopwell.filesystem.public', (string) $arg2);
    }

    public function testCorsHeaderProviderAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('cors_header_provider', (new Definition(ExampleCorsHeaderProvider::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->getDefinition('cors_header_provider')->hasTag(CorsHeaderProviderInterface::SERVICE_TAG));
    }

    public function testAttribute(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $definition = new Definition(ExampleEntity::class);
        $definition->setPublic(true);
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);

        $container->setDefinition(ExampleEntity::class, $definition);

        $container->compile();

        static::assertArrayHasKey('shopwell.entity', $container->getDefinition(ExampleEntity::class)->getTags());
    }
}

/**
 * @internal
 */
class ExampleHookableEntity implements HookableEntityInterface
{
}

/**
 * @internal
 */
class ExampleCorsHeaderProvider implements CorsHeaderProviderInterface
{
    public function provide(CorsHeaders $headers): void
    {
        $headers->addAllowed('sw-example');
    }
}

/**
 * @internal
 */
class ExampleService
{
    public function __construct(
        public FilesystemOperator $privateFilesystem,
        public FilesystemOperator $publicFilesystem
    ) {
    }
}

/**
 * @internal
 */
#[Entity('foo')]
class ExampleEntity
{
}
