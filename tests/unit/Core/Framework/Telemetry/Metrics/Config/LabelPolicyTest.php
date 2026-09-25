<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\LabelPolicy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LabelPolicy::class)]
class LabelPolicyTest extends TestCase
{
    public function testValuesReturnsAllCaseValues(): void
    {
        $values = LabelPolicy::values();

        static::assertSame(['replace', 'discard', 'open'], $values);
    }
}
