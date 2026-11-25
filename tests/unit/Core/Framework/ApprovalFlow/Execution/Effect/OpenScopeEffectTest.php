<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Effect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\OpenScopeEffect;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenScopeEffect::class)]
class OpenScopeEffectTest extends TestCase
{
    public function testOutputSelectionDoesNotRetainCallerReferences(): void
    {
        $port = 'out';
        $effect = new OpenScopeEffect([&$port], 'join');

        $port = 'changed';

        static::assertSame(['out'], $effect->outputPorts);
    }
}
