<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * Fired after a provider accepted a message.
 *
 * The delivery is recorded next to this event, the event is what the flow builder, a webhook or an
 * app consumes.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsSentEvent implements ShopwellEvent
{
    public function __construct(
        private readonly SmsMessage $message,
        private readonly string $technicalName,
        private readonly ?string $gatewayId,
        private readonly ?string $providerMessageId,
        private readonly Context $context,
    ) {
    }

    public function getMessage(): SmsMessage
    {
        return $this->message;
    }

    /**
     * The provider that carried the message, e.g. aliyun or null.
     */
    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
