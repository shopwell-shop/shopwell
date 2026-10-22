<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * A base declaration whose parts a test can set, so one fixture covers every validation the factory
 * performs.
 *
 * It deliberately does **not** override `getDecorated()`: that is what makes it a base implementation
 * instead of a decorator, which is exactly the difference the compiler pass checks.
 *
 * @internal
 */
#[Package('framework')]
class TestPaymentProvider extends AbstractPaymentProvider
{
    /**
     * @param list<AbstractPaymentAction> $actions
     * @param list<PaymentConfigField> $credentials
     * @param list<PaymentChannel> $channels
     * @param list<PaymentCapability> $declaredCapabilities
     * @param array<string, AbstractPaymentAction> $channelCaptureActions
     * @param list<mixed> $apiArguments
     * @param string $apiClass the api of the declaration; a plain string so that a test may hand in a class that is not a payment api at all
     */
    public function __construct(
        private readonly string $name = 'testpay',
        private readonly string $label = 'test.provider.label',
        private readonly array $actions = [],
        private readonly array $credentials = [],
        private readonly array $channels = [],
        private readonly array $declaredCapabilities = [],
        private readonly ?PaymentNotificationExtractorInterface $extractor = new TestNotificationExtractor(),
        private readonly ?PaymentTransferNotificationVerifierInterface $verifier = null,
        private readonly ?PayParamsDecoderInterface $decoder = null,
        private readonly array $channelCaptureActions = [],
        private readonly array $apiArguments = [],
        private readonly string $apiClass = TestPaymentApi::class,
        private readonly ?PaymentTransferRules $transferRules = null,
        private readonly ?PaymentCycleRules $cycleRules = null,
        private readonly ?string $sourceName = null,
        private readonly ?string $description = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getApiClass(): string
    {
        // the fixture keeps the api as a plain string so a test can provoke the "not a payment api"
        // guard of the factory; a real declaration never does that
        /** @var class-string<AbstractPaymentApi> $class */
        $class = $this->apiClass;

        return $class;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getCaptureAction(): AbstractPaymentAction
    {
        foreach ($this->actions as $action) {
            if ($action->capability() === PaymentCapability::CAPTURE) {
                return $action;
            }
        }

        throw new \RuntimeException('The fixture declares no capture action.');
    }

    public function getNotificationExtractor(): PaymentNotificationExtractorInterface
    {
        \assert($this->extractor instanceof PaymentNotificationExtractorInterface);

        return $this->extractor;
    }

    public function getCredentials(): array
    {
        return $this->credentials;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function getChannelCaptureActions(): array
    {
        return $this->channelCaptureActions;
    }

    public function getApiArguments(): array
    {
        return $this->apiArguments;
    }

    public function getPayParamsDecoder(): ?PayParamsDecoderInterface
    {
        return $this->decoder;
    }

    public function getDeclaredCapabilities(): array
    {
        return $this->declaredCapabilities;
    }

    public function getTransferRules(): ?PaymentTransferRules
    {
        return $this->transferRules;
    }

    public function getCycleRules(): ?PaymentCycleRules
    {
        return $this->cycleRules;
    }

    public function getTransferNotificationVerifier(): ?PaymentTransferNotificationVerifierInterface
    {
        return $this->verifier;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
