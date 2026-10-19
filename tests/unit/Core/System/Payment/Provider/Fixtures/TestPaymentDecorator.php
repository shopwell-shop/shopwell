<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * A decorator that forwards everything to the declaration it wraps, which is the only supported way to
 * extend an interface.
 *
 * The two knobs are the two mistakes this pattern makes easy: an extra action that is not forwarded
 * into `getActions()`, and a credential list that was written from scratch instead of forwarded - the
 * second one silently loses a stored merchant field.
 *
 * @internal
 */
#[Package('framework')]
class TestPaymentDecorator extends AbstractPaymentProvider
{
    /**
     * @param list<AbstractPaymentAction> $extraActions
     * @param list<PaymentConfigField>|null $credentialsOverride
     * @param string|null $nameOverride the technical name this decorator claims instead of forwarding it
     */
    public function __construct(
        private readonly AbstractPaymentProvider $decorated,
        private readonly array $extraActions = [],
        private readonly ?array $credentialsOverride = null,
        private readonly ?string $nameOverride = null,
    ) {
    }

    public function getDecorated(): AbstractPaymentProvider
    {
        return $this->decorated;
    }

    public function getName(): string
    {
        return $this->nameOverride ?? $this->decorated->getName();
    }

    public function getLabel(): string
    {
        return $this->decorated->getLabel();
    }

    public function getApiClass(): string
    {
        return $this->decorated->getApiClass();
    }

    public function getActions(): array
    {
        return [...$this->decorated->getActions(), ...$this->extraActions];
    }

    public function getCaptureAction(): AbstractPaymentAction
    {
        return $this->decorated->getCaptureAction();
    }

    public function getNotificationExtractor(): PaymentNotificationExtractorInterface
    {
        return $this->decorated->getNotificationExtractor();
    }

    public function getCredentials(): array
    {
        return $this->credentialsOverride ?? $this->decorated->getCredentials();
    }

    public function getChannels(): array
    {
        return $this->decorated->getChannels();
    }

    public function getChannelCaptureActions(): array
    {
        return $this->decorated->getChannelCaptureActions();
    }

    public function getApiArguments(): array
    {
        return $this->decorated->getApiArguments();
    }

    public function getPayParamsDecoder(): ?PayParamsDecoderInterface
    {
        return $this->decorated->getPayParamsDecoder();
    }

    public function getDeclaredCapabilities(): array
    {
        return $this->decorated->getDeclaredCapabilities();
    }

    public function getTransferRules(): ?PaymentTransferRules
    {
        return $this->decorated->getTransferRules();
    }

    public function getCycleRules(): ?PaymentCycleRules
    {
        return $this->decorated->getCycleRules();
    }

    public function getTransferNotificationVerifier(): ?PaymentTransferNotificationVerifierInterface
    {
        return $this->decorated->getTransferNotificationVerifier();
    }

    public function getSourceName(): ?string
    {
        return $this->decorated->getSourceName();
    }

    public function getDescription(): ?string
    {
        return $this->decorated->getDescription();
    }
}
