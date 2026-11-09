<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopwell\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedBindingSpecificationDto::class)]
class ResolvedBindingSpecificationDtoTest extends TestCase
{
    public function testConversionCarriesLoadedIdentitySourceAndWiring(): void
    {
        $resolved = new ResolvedBindingSpecificationDto('featured', 'app:catalog', new BindingSpecificationDto(
            'Sw:Product',
            'Featured',
            ['product' => ['loader' => 'entity', 'config' => ['entity' => 'product']]],
            ['limit' => ['default' => 0, 'required' => false]],
        ));

        $specification = $resolved->toSpecification();

        static::assertSame('app:catalog:featured', $specification->qualifiedId());
        static::assertSame('Sw:Product', $specification->type());
        static::assertSame('Featured', $specification->label());
        static::assertSame('entity', $specification->resolves()['product']->loader);
        static::assertSame(['entity' => 'product'], $specification->resolves()['product']->config);
        static::assertTrue($specification->inputs()['limit']->hasDefault);
        static::assertSame(0, $specification->inputs()['limit']->default);
        static::assertFalse($specification->inputs()['limit']->required);
    }

    public function testSourceQualifiesOtherwiseIdenticalLoadedIds(): void
    {
        $dto = new BindingSpecificationDto('Sw:Text', 'Text', [], []);
        $first = (new ResolvedBindingSpecificationDto('text', 'first', $dto))->toSpecification();
        $second = (new ResolvedBindingSpecificationDto('text', 'second', $dto))->toSpecification();

        static::assertSame('first:text', $first->qualifiedId());
        static::assertSame('second:text', $second->qualifiedId());
        static::assertSame([], $second->resolves());
        static::assertSame([], $second->inputs());
    }
}
