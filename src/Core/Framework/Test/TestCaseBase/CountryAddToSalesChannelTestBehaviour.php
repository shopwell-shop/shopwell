<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\TestCaseBase;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CountryAddToSalesChannelTestBehaviour
{
    abstract protected static function getContainer(): ContainerInterface;

    abstract protected function getValidCountryId(?string $salesChannelId = TestDefaults::SALES_CHANNEL): string;

    /**
     * @param array<string> $additionalCountryIds
     */
    protected function addCountriesToSalesChannel(array $additionalCountryIds = [], string $salesChannelId = TestDefaults::SALES_CHANNEL): void
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $countryIds = array_merge([
            ['id' => $this->getValidCountryId($salesChannelId)],
        ], array_map(static fn (string $countryId) => ['id' => $countryId], $additionalCountryIds));

        $salesChannelRepository->update([[
            'id' => $salesChannelId,
            'countries' => $countryIds,
        ]], Context::createDefaultContext());
    }
}
