<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;

/**
 * The parameters an app needs to hand the payer over to the channel. Which of them are set depends on
 * the {@see PaymentFlow} of the channel.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PayParams implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $extra channel specific parameters, e.g. the payload of an sdk flow
     */
    public function __construct(
        public PaymentFlow $flow,
        public ?string $payUrl = null,
        public string $httpMethod = 'GET',
        public ?string $htmlForm = null,
        public array $extra = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = ['flow' => $this->flow->value];

        if ($this->payUrl !== null) {
            $payload['pay_url'] = $this->payUrl;
            $payload['http_method'] = $this->httpMethod;
        }

        if ($this->htmlForm !== null) {
            $payload['html_form'] = $this->htmlForm;
        }

        if ($this->extra !== []) {
            $payload['extra'] = $this->extra;
        }

        return $payload;
    }
}
