<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Salutation\SalutationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalesChannelSalutationDefinition extends SalutationDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
    }
}
