<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopwell\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDefinitionInstanceRegistry::class)]
class SalesChannelDefinitionInstanceRegistryTest extends TestCase
{
    public function testRegister(): void
    {
        $registry = new SalesChannelDefinitionInstanceRegistry(
            'sales_channel_definition.',
            new Container(),
            [],
            []
        );

        $registry->register(new ProductDefinition());

        static::assertInstanceOf(ProductDefinition::class, $registry->get(ProductDefinition::class));
        static::assertTrue($registry->has(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(ProductDefinition::class, $registry->getByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(ProductDefinition::class, $registry->getByEntityClass(new ProductEntity()));
    }

    public function testItThrowsExceptionWhenSalesChannelRepositoryWasNotFoundByEntityName(): void
    {
        $registry = new SalesChannelDefinitionInstanceRegistry(
            'sales_channel_definition.',
            new Container(),
            [],
            []
        );

        $this->expectException(SalesChannelRepositoryNotFoundException::class);
        try {
            $registry->getSalesChannelRepository('fooBar');
        } catch (SalesChannelRepositoryNotFoundException $exception) {
            static::assertSame(SalesChannelRepositoryNotFoundException::class, $exception::class);
            static::assertSame(500, $exception->getStatusCode());
            static::assertSame('FRAMEWORK__SALES_CHANNEL_REPOSITORY_NOT_FOUND', $exception->getErrorCode());
            static::assertSame('SalesChannelRepository for entity "fooBar" does not exist.', $exception->getMessage());
            static::assertSame(['entityName' => 'fooBar'], $exception->getParameters());

            throw $exception;
        }
    }

    public function testMissingSalesChannelRepositoryFallsBackToTheCoreRepository(): void
    {
        $repository = static::createStub(EntityRepository::class);
        $container = new Container();
        $container->set('fooBar.repository', $repository);
        $core = new DefinitionInstanceRegistry($container, [], ['fooBar' => 'fooBar.repository']);
        $salesChannel = new SalesChannelDefinitionInstanceRegistry('sales_channel_definition.', $container, [], []);
        $chain = new DefinitionRegistryChain($core, $salesChannel);

        static::assertSame($repository, $chain->getRepository('fooBar'));
    }
}
