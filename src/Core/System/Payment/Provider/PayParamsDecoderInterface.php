<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;

/**
 * Turns the answer an interface recorded for its last capture back into the parameters a payer has to
 * be handed to.
 *
 * Reopening a payment must not open a second trade at the interface, so a payer who comes back, reloads
 * or switches the device is handed to the answer the channel already gave. Only the interface knows the
 * shape of its own answer - the keys are the ones its capture action records - so the decode belongs to
 * the interface and not to the checkout that asks for it. An interface without a decoder is simply
 * captured again, which is correct but costs a second call at the channel.
 *
 * @internal
 */
#[Package('framework')]
interface PayParamsDecoderInterface
{
    /**
     * @param array<string, mixed> $response the recorded response payload of the last capture
     *
     * @return PayParams|null null when the answer does not carry enough to hand the payer over
     */
    public function payParamsFromResponse(array $response): ?PayParams;
}
