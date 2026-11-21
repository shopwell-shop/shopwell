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
use Shopwell\Core\System\Language\LanguageCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://languages',
    name: 'shopwell-languages',
    description: 'All configured languages with locale codes.'
)]
class LanguageListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
    ) {
    }

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');

        $result = $this->languageRepository->search($criteria, Context::createDefaultContext());

        $languages = [];
        foreach ($result->getEntities() as $language) {
            $languages[] = [
                'id' => $language->getId(),
                'name' => $language->getName(),
                'localeCode' => $language->getLocale()?->getCode(),
            ];
        }

        return McpResourceCacheHint::read([
            'uri' => 'shopwell://languages',
            'mimeType' => 'application/json',
            'text' => Json::encode($languages),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
