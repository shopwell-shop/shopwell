<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Layout\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\StoredDefaultProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredDefaultProvider::class)]
class StoredDefaultProviderTest extends TestCase
{
    #[TestDox('returns top-level defaults, skipping properties without defaults and references')]
    public function testForTypeSkipsNullDefaultsAndReferences(): void
    {
        $specs = [
            'Sw:Mixed' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Mixed')
                ->primitive('withDefault', 'string', default: 'seeded')
                ->primitive('noDefault', 'string', required: true)
                ->reference('product', SalesChannelProductEntity::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(['withDefault' => 'seeded'], (new StoredDefaultProvider())->forType($registry, 'Sw:Mixed'));
    }

    #[TestDox('returns nested object member defaults')]
    public function testForTypeReturnsNestedObjectDefaults(): void
    {
        $nestedProperties = [
            'xs' => new PropertySpecification('xs', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
            'sm' => new PropertySpecification('sm', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
        ];
        $specs = [
            'Sw:Grid:Container' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Grid:Container', 'Grid Container')
                ->declared('padding', ['string', 'object'], properties: $nestedProperties)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(
            ['padding' => ['xs' => '0 20px 0 20px', 'sm' => '0 20px 0 20px']],
            (new StoredDefaultProvider())->forType($registry, 'Sw:Grid:Container'),
        );
    }
}
