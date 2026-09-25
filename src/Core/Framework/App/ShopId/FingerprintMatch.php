<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ShopId;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class FingerprintMatch
{
    public function __construct(
        public string $identifier,
        public string $storedStamp,
        public int $score,
    ) {
    }

    public static function fromFingerprint(Fingerprint $fingerprint): self
    {
        return new self(
            $fingerprint->getIdentifier(),
            $fingerprint->getStamp(),
            $fingerprint->getScore()
        );
    }
}
