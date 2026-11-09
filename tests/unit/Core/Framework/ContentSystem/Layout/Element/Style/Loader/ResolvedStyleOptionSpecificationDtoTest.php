<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Loader\ResolvedStyleOptionSpecificationDto;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedStyleOptionSpecificationDto::class)]
class ResolvedStyleOptionSpecificationDtoTest extends TestCase
{
    public function testConversionCarriesSourceNameAndValueRestrictions(): void
    {
        $resolved = new ResolvedStyleOptionSpecificationDto('columns', 'app:theme', new StyleOptionSpecificationDto(
            'integer',
            [0, 2, 4],
            ['min' => 0, 'max' => 4],
            null,
            0,
            false,
            ['component' => 'number-field'],
        ));

        $specification = $resolved->toSpecification();

        static::assertSame('columns', $specification->name());
        static::assertSame('app:theme', $specification->source());
        static::assertSame([
            'type' => 'integer', 'enum' => [0, 2, 4], 'range' => ['min' => 0, 'max' => 4],
            'maxLength' => null, 'default' => 0, 'breakpointAware' => false, 'adminUI' => ['component' => 'number-field'],
        ], $specification->toSchema());
    }

    public function testConversionRetainsKindAndDefaultBreakpointBehavior(): void
    {
        $resolved = new ResolvedStyleOptionSpecificationDto('padding', 'theme', new StyleOptionSpecificationDto(
            'string',
            null,
            null,
            40,
            '0',
            null,
            [],
            'box-spacing',
        ));

        $specification = $resolved->toSpecification();

        static::assertSame('box-spacing', $specification->kind());
        static::assertTrue($specification->breakpointAware());
        static::assertNull($specification->adminUI());
        static::assertSame('0', $specification->toSchema()['default']);
        static::assertSame(40, $specification->toSchema()['maxLength']);
    }
}
