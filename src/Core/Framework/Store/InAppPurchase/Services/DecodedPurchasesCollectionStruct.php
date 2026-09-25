<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\InAppPurchase\Services;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\Framework\Struct\Collection;
use Shopwell\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 *
 * @template-extends Collection<DecodedPurchaseStruct>
 */
#[Package('checkout')]
class DecodedPurchasesCollectionStruct extends Collection
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $elements = ['elements' => \array_map(static function (array $element): DecodedPurchaseStruct {
            $dto = ValidatorFactory::create($element, DecodedPurchaseStruct::class, true);
            if (!$dto instanceof DecodedPurchaseStruct) {
                throw StoreException::invalidType(DecodedPurchaseStruct::class, $dto::class);
            }

            return $dto;
        }, $data)];

        return (new self())->assign($elements);
    }
}
