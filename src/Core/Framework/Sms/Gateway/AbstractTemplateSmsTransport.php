<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\SmsException;
use Shopwell\Core\Framework\Sms\SmsUnrecoverableException;
use Shopwell\Core\Framework\Sms\Struct\TemplateSmsOptionsInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The base of a provider that sends templates reported at its side.
 *
 * It holds what every one of them shares - the number, the signature and the parameters of the
 * template - so the transport of a provider only builds its request and reads its response. The
 * events of the notifier are dispatched by AbstractTransport, the delivery record and the failure
 * classification of the component hang off them.
 *
 * The difference between a template based gateway and one that sends free text is decided before the
 * transport is even built: SmsTemplateResolver reads supportsTemplateMode() of the factory, so a
 * message that reaches here carries a template. A message without one is a caller that addressed the
 * gateway directly with the wrong options, which is what templateData reports.
 *
 * The providers talk to their endpoint through the sdk of the provider, which brings its own http
 * stack, so the client of the notifier stays unused.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractTemplateSmsTransport extends AbstractTransport
{
    public function __construct(?EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct(null, $dispatcher);
    }

    final public function __toString(): string
    {
        return $this->getTechnicalName();
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    abstract protected function getTechnicalName(): string;

    /**
     * The signature to fall back to when neither the template mapping nor the message named one.
     */
    abstract protected function getDefaultSignName(): ?string;

    /**
     * The three things a provider of reported templates needs from a message.
     *
     * @throws SmsException
     * @throws SmsUnrecoverableException
     *
     * @return array{templateCode: string, signName: string, params: array<string, string>}
     */
    final protected function templateData(MessageInterface $message): array
    {
        $options = $message->getOptions();

        if (!$options instanceof TemplateSmsOptionsInterface) {
            throw SmsException::templateModeRequired($this->getTechnicalName());
        }

        $templateCode = $options->getTemplateCode();

        if ($templateCode === null || $templateCode === '') {
            throw SmsException::templateModeRequired($this->getTechnicalName());
        }

        $signName = $options->getSignName() ?? $this->getDefaultSignName();

        if ($signName === null || $signName === '') {
            throw SmsUnrecoverableException::signNameMissing($this->getTechnicalName());
        }

        return [
            'templateCode' => $templateCode,
            'signName' => $signName,
            'params' => $options->getParams(),
        ];
    }

    /**
     * The number as e.164, the only form the transports build their request from.
     *
     * @throws SmsException
     */
    final protected function recipient(MessageInterface $message): string
    {
        $recipient = $message->getRecipientId();

        if ($recipient === null || $recipient === '') {
            throw SmsException::invalidPhoneNumber('');
        }

        return $recipient;
    }

    /**
     * Turns an answer of the provider that is not a success into the failure the pipeline records.
     *
     * The codes the provider marks as retryable are looked up first, everything else is final - a
     * template that is not reported, a signature that is not approved and a number the provider
     * blocked stay refused on every further attempt.
     *
     * @param list<string> $retryablePrefixes
     *
     * @throws SmsException
     * @throws SmsUnrecoverableException
     */
    final protected function fail(string $providerCode, string $providerMessage, array $retryablePrefixes): never
    {
        foreach ($retryablePrefixes as $prefix) {
            if (str_starts_with($providerCode, $prefix)) {
                throw SmsException::providerUnavailable($this->getTechnicalName(), $providerCode, $providerMessage);
            }
        }

        throw SmsUnrecoverableException::providerRejected($this->getTechnicalName(), $providerCode, $providerMessage);
    }
}
