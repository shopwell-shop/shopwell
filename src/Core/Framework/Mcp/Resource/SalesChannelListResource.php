<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://sales-channels',
    name: 'shopwell-sales-channels',
    description: 'All sales channels with their IDs, names, types, and domains.'
)]
class SalesChannelListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');

        $result = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());

        $channels = [];
        foreach ($result->getEntities() as $channel) {
            $domains = [];
            foreach ($channel->getDomains() ?? [] as $domain) {
                $domains[] = [
                    'url' => $domain->getUrl(),
                    'languageId' => $domain->getLanguageId(),
                ];
            }

            $channels[] = [
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'type' => $channel->getType()?->getName(),
                'active' => $channel->getActive(),
                'domains' => $domains,
            ];
        }

        return [
            'uri' => 'shopwell://sales-channels',
            'mimeType' => 'application/json',
            'text' => Json::encode($channels),
        ];
    }
}
