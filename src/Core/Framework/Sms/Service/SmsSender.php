<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\Framework\Sms\Message\SendLowPrioritySmsMessage;
use Shopwell\Core\Framework\Sms\Message\SendSmsMessage;
use Shopwell\Core\Framework\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\Framework\Sms\Template\SmsTemplateResolver;
use Shopwell\Core\Framework\Sms\Transport\SmsTransports;
use Shopwell\Core\Framework\Sms\Util\PhoneNumberNormalizer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * The default sender.
 *
 * It normalises the number, makes sure the message knows its gateway, decides whether the message is
 * sent right away or queued and hands it to the transports.
 *
 * The decision of the queue is made here and deliberately not left to the notifier, whose own bus
 * would skip the delivery record and the failure classification of the component.
 *
 * @internal
 */
#[Package('framework')]
class SmsSender extends AbstractSmsSender
{
    public function __construct(
        private readonly SmsTransports $transports,
        private readonly SmsTemplateResolver $templateResolver,
        private readonly SmsGatewayResolverInterface $gatewayResolver,
        private readonly PhoneNumberNormalizer $normalizer,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function getDecorated(): AbstractSmsSender
    {
        throw new DecorationPatternException(self::class);
    }

    public function send(SmsMessage $message, ?Context $context = null, ?string $salesChannelId = null): ?SentMessage
    {
        $context ??= Context::createDefaultContext();

        $phone = $this->normalizer->toE164($message->getPhone(), $salesChannelId);
        $message->phone($phone);

        if ($message->getTransport() === null) {
            $gatewayIds = $this->gatewayResolver->resolveGatewayIds($context, $salesChannelId, $this->normalizer->countryCode($phone));

            // no gateway matched, the message is recorded but does not leave the platform
            $message->transport($gatewayIds[0] ?? SmsGatewayResolverInterface::GATEWAY_NULL);
        }

        if ($this->shouldQueue($message)) {
            $this->bus->dispatch($this->envelopeFor($message));

            return null;
        }

        return $this->transports->send($message);
    }

    public function sendTemplate(
        string $templateRef,
        array $params,
        string $phone,
        ?Context $context = null,
        ?string $salesChannelId = null,
    ): ?SentMessage {
        $context ??= Context::createDefaultContext();

        return $this->send($this->templateResolver->resolve($templateRef, $params, $phone, $context, $salesChannelId), $context, $salesChannelId);
    }

    /**
     * A verification code is awaited by the customer, it must not wait for the queue.
     */
    private function shouldQueue(SmsMessage $message): bool
    {
        return $this->sceneOf($message) !== SmsTemplateEntity::SCENE_OTP;
    }

    private function envelopeFor(SmsMessage $message): SendSmsMessage|SendLowPrioritySmsMessage
    {
        return $this->sceneOf($message) === SmsTemplateEntity::SCENE_MARKETING
            ? new SendLowPrioritySmsMessage($message)
            : new SendSmsMessage($message);
    }

    private function sceneOf(SmsMessage $message): string
    {
        $options = $message->getOptions();

        return $options instanceof SmsMessageOptions ? $options->getScene() : SmsTemplateEntity::SCENE_TRANSACTION;
    }
}
