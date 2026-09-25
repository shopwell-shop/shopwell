<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ResetOnClone;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResetOnClone::class)]
class ResetOnCloneTest extends TestCase
{
    public function testParse(): void
    {
        static::assertSame(['reset_on_clone' => true], iterator_to_array((new ResetOnClone())->parse()));
    }
}
