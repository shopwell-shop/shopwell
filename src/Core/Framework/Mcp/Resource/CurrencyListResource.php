<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;
use Shopwell\Core\System\Currency\CurrencyCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://currencies',
    name: 'shopwell-currencies',
    description: 'All configured currencies with ISO codes, symbols, and conversion factors.'
)]
class CurrencyListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private readonly EntityRepository $currencyRepository,
    ) {
    }

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
    {
        $result = $this->currencyRepository->search(new Criteria(), Context::createDefaultContext());

        $currencies = [];
        foreach ($result->getEntities() as $currency) {
            $currencies[] = [
                'id' => $currency->getId(),
                'isoCode' => $currency->getIsoCode(),
                'symbol' => $currency->getSymbol(),
                'factor' => $currency->getFactor(),
                'name' => $currency->getName(),
            ];
        }

        return McpResourceCacheHint::read([
            'uri' => 'shopwell://currencies',
            'mimeType' => 'application/json',
            'text' => Json::encode($currencies),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
