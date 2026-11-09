<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextType::class)]
class ContextTypeTest extends TestCase
{
    #[TestDox('returns all case values as list of strings')]
    public function testValuesReturnsAllCaseValues(): void
    {
        $values = ContextType::values();

        static::assertSame(['single', 'collection'], $values);
    }
}
