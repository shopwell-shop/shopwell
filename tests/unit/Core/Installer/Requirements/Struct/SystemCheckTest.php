<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Installer\Requirements\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopwell\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheck::class)]
class SystemCheckTest extends TestCase
{
    public function testGetters(): void
    {
        $check = new SystemCheck('name', RequirementCheck::STATUS_SUCCESS, 'requiredValue', 'installedValue');

        static::assertSame('name', $check->getName());
        static::assertSame('requiredValue', $check->getRequiredValue());
        static::assertSame('installedValue', $check->getInstalledValue());
        static::assertSame(RequirementCheck::STATUS_SUCCESS, $check->getStatus());
    }
}
