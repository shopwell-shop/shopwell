<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Api\Serializer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - will be removed, use \Shopwell\Core\Test\Assert\ArrayValues::assertValues() instead
 */
trait AssertValuesTrait
{
    /**
     * @deprecated tag:v6.8.0 - will be removed, use \Shopwell\Core\Test\Assert\ArrayValues::assertValues() instead
     *
     * @param array<int|string, mixed> $expected
     * @param array<int|string, mixed> $actual
     */
    protected function assertValues(array $expected, array $actual): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '\Shopwell\Core\Test\Assert\ArrayValues::assertValues()'));

        foreach ($expected as $key => $value) {
            TestCase::assertArrayHasKey($key, $actual);

            if (\is_array($value)) {
                $this->assertValues($value, $actual[$key]);
            } else {
                TestCase::assertSame($value, $actual[$key]);
            }
        }
    }
}
