<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * Fired after a provider refused a message or could not be reached.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsFailedEvent implements ShopwellEvent
{
    public function __construct(
        private readonly SmsMessage $message,
        private readonly string $technicalName,
        private readonly ?string $gatewayId,
        private readonly \Throwable $error,
        private readonly Context $context,
    ) {
    }

    public function getMessage(): SmsMessage
    {
        return $this->message;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
