<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Integration\Traits;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
trait CustomerTestTrait
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private function getLoggedInContextToken(string $customerId, string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        $token = Random::getAlphanumericString(32);
        static::getContainer()->get(SalesChannelContextPersister::class)->save(
            $token,
            [
                'customerId' => $customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $salesChannelId,
            $customerId
        );

        return $token;
    }
}
