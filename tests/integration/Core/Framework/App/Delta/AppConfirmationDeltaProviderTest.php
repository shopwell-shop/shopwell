<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Delta;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppConfirmationDeltaProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetDeltas(): void
    {
        $deltas = $this->getAppConfirmationDeltaProvider()
            ->getReports(
                $this->getTestManifest(),
                new AppEntity()
            );

        static::assertCount(2, $deltas);
        static::assertArrayHasKey('permissions', $deltas);
        static::assertCount(6, $deltas['permissions']);
        static::assertArrayHasKey('domains', $deltas);
        static::assertCount(8, $deltas['domains']);
    }

    public function testRequiresRenewedConsent(): void
    {
        $appConfirmationDeltaProvider = $this->getAppConfirmationDeltaProvider();

        $requiresRenewedConsent = $appConfirmationDeltaProvider->requiresRenewedConsent(
            $this->getTestManifest(),
            new AppEntity()
        );
        static::assertTrue($requiresRenewedConsent);
    }

    private function getTestManifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }

    private function getAppConfirmationDeltaProvider(): AppConfirmationDeltaProvider
    {
        return static::getContainer()
            ->get(AppConfirmationDeltaProvider::class);
    }
}
