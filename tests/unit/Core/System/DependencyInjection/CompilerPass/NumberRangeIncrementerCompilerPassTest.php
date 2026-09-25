<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\DependencyInjection\CompilerPass\NumberRangeIncrementerCompilerPass;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeIncrementerCompilerPass::class)]
class NumberRangeIncrementerCompilerPassTest extends TestCase
{
    public function testRemovesRedisServicesWhenConnectionIsNull(): void
    {
        $container = new ContainerBuilder();
        $container->addDefinitions([
            IncrementRedisStorage::class => new Definition(),
            'shopwell.number_range.redis' => new Definition(),
            IncrementSqlStorage::class => new Definition(),
        ]);
        $container->setParameter('shopwell.number_range.config.connection', null);

        $compilerPass = new NumberRangeIncrementerCompilerPass();
        $compilerPass->process($container);

        static::assertFalse($container->hasDefinition(IncrementRedisStorage::class));
        static::assertFalse($container->hasDefinition('shopwell.number_range.redis'));
        static::assertTrue($container->hasDefinition(IncrementSqlStorage::class));
    }

    public function testKeepsRedisServicesWhenConnectionIsConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->addDefinitions([
            IncrementRedisStorage::class => new Definition(),
            'shopwell.number_range.redis' => new Definition(),
            IncrementSqlStorage::class => new Definition(),
        ]);
        $container->setParameter('shopwell.number_range.config.connection', 'my_connection');

        $compilerPass = new NumberRangeIncrementerCompilerPass();
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition(IncrementRedisStorage::class));
        static::assertTrue($container->hasDefinition('shopwell.number_range.redis'));
        static::assertTrue($container->hasDefinition(IncrementSqlStorage::class));
    }
}
