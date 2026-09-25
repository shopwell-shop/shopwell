<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\InAppPurchases\Payload;

use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InAppPurchasesPayload implements SourcedPayloadInterface
{
    use JsonSerializableTrait;

    public Source $source;

    /**
     * @param array<int, string> $purchases
     */
    public function __construct(public readonly array $purchases)
    {
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
