<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;

/**
 * A notification extractor that reads nothing - the declaration only has to name one.
 *
 * @internal
 */
#[Package('framework')]
final class TestNotificationExtractor implements PaymentNotificationExtractorInterface
{
    public function extract(string $rawBody, array $headers): PaymentNotificationData
    {
        return new PaymentNotificationData();
    }

    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('ok');
    }
}
