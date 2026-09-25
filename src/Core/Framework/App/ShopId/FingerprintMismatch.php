<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ShopId;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class FingerprintMismatch
{
    public function __construct(
        public string $identifier,
        public ?string $storedStamp,
        public string $expectedStamp,
        public int $score,
    ) {
    }
}
