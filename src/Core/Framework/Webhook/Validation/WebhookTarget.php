<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Validation;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WebhookTarget
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $ip,
    ) {
    }
}
