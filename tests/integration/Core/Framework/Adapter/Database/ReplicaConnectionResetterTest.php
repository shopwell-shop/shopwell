<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Adapter\Database;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetterTest extends TestCase
{
    use KernelTestBehaviour;

    // Remove this test with the Framework::boot() workaround once a Symfony upgrade proves the resetter initializes this service.
    public function testServicesResetterInitializesReplicaConnectionResetter(): void
    {
        $container = static::getContainer();

        $servicesResetter = $container->get('services_resetter');
        static::assertInstanceOf(ServicesResetterInterface::class, $servicesResetter);
        $servicesResetter->reset();

        // Once Symfony initializes all kernel.reset services itself, this remains true without
        // Framework::boot() fetching the service and the boot-time workaround can be removed.
        static::assertTrue($container->initialized(ReplicaConnectionResetter::class));
    }
}
