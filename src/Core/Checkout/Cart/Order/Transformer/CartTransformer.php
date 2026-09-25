<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order\Transformer;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopwell\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\Clock;

#[Package('checkout')]
class CartTransformer
{
    /**
     * @return array<string, mixed>
     */
    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'setOrderDate', newName: 'setPersistentData')]
    public static function transform(Cart $cart, SalesChannelContext $context, string $stateId, bool $setOrderDate = true): array
    {
        $currency = $context->getCurrency();

        $data = [
            'price' => $cart->getPrice(),
            'shippingCosts' => $cart->getShippingCosts(),
            'stateId' => $stateId,
            'currencyId' => $currency->getId(),
            'currencyFactor' => $currency->getFactor(),
            'salesChannelId' => $context->getSalesChannelId(),
            'lineItems' => [],
            'deliveries' => [],
            'customerComment' => $cart->getCustomerComment(),
            'affiliateCode' => $cart->getAffiliateCode(),
            'campaignCode' => $cart->getCampaignCode(),
            'source' => $cart->getSource(),
        ];

        if ($setOrderDate) {
            $data['orderDateTime'] = Clock::get()->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $data['deepLinkCode'] = Random::getBase64UrlString(32);
        }

        $source = $context->getContext()->getSource();
        if ($source instanceof AdminSalesChannelApiSource) {
            $originalContextSource = $source->getOriginalContext()->getSource();
            if ($originalContextSource instanceof AdminApiSource) {
                $data['createdById'] = $originalContextSource->getUserId();
            }
        }

        $data['itemRounding'] = json_decode(Json::encode($context->getItemRounding()), true, 512, \JSON_THROW_ON_ERROR);
        $data['totalRounding'] = json_decode(Json::encode($context->getTotalRounding()), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
