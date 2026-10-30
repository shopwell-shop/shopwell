<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Authorization\Ownership;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Ownership
{
    public function __construct(
        public readonly string $webhookId,
        public readonly ?string $appId,
    ) {
    }

    public function belongsToApp(): bool
    {
        return $this->appId !== null;
    }
}
