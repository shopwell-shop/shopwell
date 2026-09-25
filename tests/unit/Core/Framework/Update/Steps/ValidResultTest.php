<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Update\Steps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Steps\ValidResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ValidResult::class)]
class ValidResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new ValidResult(1, 2);

        static::assertSame(1, $result->getOffset());
        static::assertSame(2, $result->getTotal());
    }
}
