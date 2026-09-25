<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Increment;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Increment\AbstractIncrementer;
use Shopwell\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopwell\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class IncrementerGatewayRegistryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetUserActivityPool(): void
    {
        $registry = static::getContainer()->get('shopwell.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::USER_ACTIVITY_POOL));
    }

    /**
     * @deprecated tag:v6.8.0 - Test will be removed
     */
    public function testGetMessageQueuePool(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $registry = static::getContainer()->get('shopwell.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL));
    }

    public function testGetWithInvalidPool(): void
    {
        $this->expectExceptionObject(new IncrementGatewayNotFoundException('custom_pool'));

        $registry = static::getContainer()->get('shopwell.increment.gateway.registry');
        $registry->get('custom_pool');
    }
}
