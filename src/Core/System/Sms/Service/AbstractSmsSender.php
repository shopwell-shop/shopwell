<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * The entry point of the business code, its counterpart is the AbstractMailSender of the mail.
 *
 * An extension may decorate the sender to add a rate limit or a blacklist without touching the
 * routing or the providers.
 */
#[Package('framework')]
abstract class AbstractSmsSender
{
    /**
     * @internal
     */
    abstract public function getDecorated(): AbstractSmsSender;

    /**
     * Sends an already built message. The gateway is resolved from the sales channel when the message
     * does not carry one, a message of a scene that may be queued is put on the queue and null is
     * returned.
     *
     * @param string|null $salesChannelId the sales channel the credentials, the default country of the number
     *                                    and the global settings are read for, null means the installation wide
     *                                    configuration
     *
     * @throws SmsException
     */
    abstract public function send(SmsMessage $message, ?Context $context = null, ?string $salesChannelId = null): ?SentMessage;

    /**
     * The recommended entry point: refers to a logical template by name and lets the component pick
     * the gateway, the template of the provider and the shape of the message.
     *
     * @param array<string, mixed> $params
     * @param string|null $salesChannelId see send()
     *
     * @throws SmsException
     */
    abstract public function sendTemplate(
        string $templateRef,
        array $params,
        string $phone,
        ?Context $context = null,
        ?string $salesChannelId = null,
    ): ?SentMessage;
}
