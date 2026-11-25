<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Effect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdvanceEffect::class)]
class AdvanceEffectTest extends TestCase
{
    public function testOutputSelectionDoesNotRetainCallerReferences(): void
    {
        $port = 'out';
        $effect = new AdvanceEffect([&$port]);

        $port = 'changed';

        static::assertSame(['out'], $effect->outputPorts);
    }
}
