<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\ScheduledTask\InstallServicesTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallServicesTask::class)]
class InstallServicesTaskTest extends TestCase
{
    public function testMeta(): void
    {
        static::assertSame('services.install', InstallServicesTask::getTaskName());
        static::assertSame(86_400, InstallServicesTask::getDefaultInterval());

        static::assertTrue(InstallServicesTask::shouldRun(new ParameterBag()));
        static::assertTrue(InstallServicesTask::shouldRescheduleOnFailure());
    }
}
