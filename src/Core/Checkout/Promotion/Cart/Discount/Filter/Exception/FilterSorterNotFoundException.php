<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Exception;

use Shopwell\Core\Checkout\Promotion\PromotionException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use PromotionException::filterSorterNotFound() instead
 */
#[Package('checkout')]
class FilterSorterNotFoundException extends PromotionException
{
    public function __construct(string $key)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            parent::FILTER_SORTER_NOT_FOUND,
            'Sorter "{{ key }}" has not been found!',
            ['key' => $key]
        );
    }
}
