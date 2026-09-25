<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<SalesChannelEntity, SalesChannelCollection>
 */
#[Package('after-sales')]
class SalesChannelProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'domains',
            'mailHeaderFooter',
        ]);

        return $criteria;
    }
}
