<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * What the platform answers a channel that notified it.
 *
 * Channels differ here and both behaviours matter: alipay expects the plain word `success` and retries
 * forever otherwise, wechat pay expects a small json document.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentNotificationAck
{
    public function __construct(
        public string $body,
        public string $contentType = 'text/plain; charset=utf-8',
    ) {
    }
}
