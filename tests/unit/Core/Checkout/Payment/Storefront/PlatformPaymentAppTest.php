<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PlatformPaymentApp::class)]
class PlatformPaymentAppTest extends TestCase
{
    public function testReadsTheExplicitPlatformTenantWithoutProvisioningAccounts(): void
    {
        $app = new PaymentAppEntity();
        $app->id = 'platform';
        $app->setUniqueIdentifier($app->id);
        $repository = new StaticEntityRepository([new PaymentAppCollection([$app])]);

        static::assertSame($app, (new PlatformPaymentApp($repository))->app());
        static::assertSame([], $repository->creates);
        static::assertSame([], $repository->upserts);
        static::assertEquals([new EqualsFilter('technicalName', 'platform'), new EqualsFilter('active', true)], $repository->searchedCriteria[0]->getFilters());
    }

    public function testMissingTenantIsReportedWithoutCreatingIt(): void
    {
        $repository = new StaticEntityRepository([new PaymentAppCollection()]);
        $this->expectExceptionObject(PaymentException::platformTenantMissing(PlatformPaymentApp::TECHNICAL_NAME));

        (new PlatformPaymentApp($repository))->app();
    }
}
