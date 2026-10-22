<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class SalesChannelDictionaryEntryDefinition extends DictionaryEntryDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
    }
}
