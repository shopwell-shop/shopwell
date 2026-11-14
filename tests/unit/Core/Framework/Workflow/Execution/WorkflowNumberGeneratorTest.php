<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNumberGenerator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowNumberGenerator::class)]
class WorkflowNumberGeneratorTest extends TestCase
{
    public function testConsecutiveNumbersRetainTheFullUuidAndRemainDistinct(): void
    {
        $generator = new WorkflowNumberGenerator();
        $numbers = [];
        for ($index = 0; $index < 100; ++$index) {
            $number = $generator->next();
            static::assertMatchesRegularExpression('/^WF[0-9]{8}[0-9A-F]{32}$/D', $number);
            static::assertLessThanOrEqual(64, \strlen($number));
            $numbers[] = $number;
        }

        static::assertCount(100, array_unique($numbers));
    }
}
