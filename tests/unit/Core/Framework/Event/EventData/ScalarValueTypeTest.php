<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScalarValueType::class)]
class ScalarValueTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'float',
        ];

        static::assertSame($expected, (new ScalarValueType(ScalarValueType::TYPE_FLOAT))->toArray());
    }

    public function testThrowExceptionOnInvalidType(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Invalid type "test" provided, valid ones are: string, int, float, bool'));

        new ScalarValueType('test');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testThrowExceptionOnInvalidTypeDeprecated(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ScalarValueType('test');
    }
}
