<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopwell\Core\Framework\Log\Package;

/**
 * @template-extends StoreCollection<ReviewStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ReviewCollection extends StoreCollection
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected function getExpectedClass(): ?string
    {
        return ReviewStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return ReviewStruct::fromArray($element);
    }
}
