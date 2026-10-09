<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Test\Stub\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\EmptyEntityExistence;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EmptyEntityExistence::class)]
class EmptyEntityExistenceTest extends TestCase
{
    public function testICanCreateStub(): void
    {
        $stub = new EmptyEntityExistence();
        static::assertSame('', $stub->getEntityName());
    }
}
