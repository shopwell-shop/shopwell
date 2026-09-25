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
 * This route is used to subscribe to the newsletter
 * The required parameters are: "email" and "option"
 * Valid "option" arguments: "subscribe" for double optin and "direct" to skip double optin
 * Optional parameters are: "salutationId", "firstName", "lastName", "street", "city" and "zipCode"
 */
#[Package('after-sales')]
abstract class AbstractNewsletterSubscribeRoute
{
    abstract public function getDecorated(): AbstractNewsletterSubscribeRoute;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Implement subscribeWithResponse() instead.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse;

    /**
     * @return StoreApiResponse<covariant Struct>
     */
    #[BecomesAbstract(version: 'v6.8.0', description: 'Implement it in your decorator; subscribe() is removed.')]
    public function subscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "subscribeWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "subscribe()" method will be removed.',
                static::class
            )
        );

        return $this->subscribe($dataBag, $context, $validateStorefrontUrl);
    }
}
