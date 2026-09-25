<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\SalesChannel;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.8.0 - will be removed as it is unused. Use AbstractContextSwitchRoute directly.
 */
#[Package('framework')]
class SalesChannelContextSwitcher
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractContextSwitchRoute $contextSwitchRoute)
    {
    }

    public function update(DataBag $data, SalesChannelContext $context): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(SalesChannelContextSwitcher::class, 'v6.8.0.0', AbstractContextSwitchRoute::class)
        );

        $this->contextSwitchRoute->switchContext($data->toRequestDataBag(), $context);
    }
}
