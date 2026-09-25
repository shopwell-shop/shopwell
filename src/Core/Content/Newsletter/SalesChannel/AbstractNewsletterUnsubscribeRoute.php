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
 * This route is used to unsubscribe the newsletter
 * The required parameters is "email"
 */
#[Package('after-sales')]
abstract class AbstractNewsletterUnsubscribeRoute
{
    abstract public function getDecorated(): AbstractNewsletterUnsubscribeRoute;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Implement unsubscribeWithResponse() instead.
     *
     * @return StoreApiResponse<covariant Struct>
     */
    abstract public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse;

    /**
     * @return StoreApiResponse<covariant Struct>
     */
    #[BecomesAbstract(version: 'v6.8.0', description: 'Implement it in your decorator; unsubscribe() is removed.')]
    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "unsubscribeWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "unsubscribe()" method will be removed.',
                static::class
            )
        );

        return $this->unsubscribe($dataBag, $context);
    }
}
