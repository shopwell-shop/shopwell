<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\SalesChannel;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * This route is used to confirm the newsletter registration
 * The required parameters are: "hash" (received from the mail) and "email"
 */
#[Package('after-sales')]
abstract class AbstractNewsletterConfirmRoute
{
    abstract public function getDecorated(): AbstractNewsletterConfirmRoute;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Implement confirmWithResponse() instead.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    /**
     * @return StoreApiResponse<covariant Struct>
     */
    #[BecomesAbstract(version: 'v6.8.0', description: 'Implement it in your decorator; confirm() is removed.')]
    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "confirmWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "confirm()" method will be removed.',
                static::class
            )
        );

        return $this->confirm($dataBag, $context);
    }
}
