<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Notification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;

/**
 * Verifies an incoming payout notification and reads the payout number out of it.
 *
 * The platform does not interpret a payout notification: the signed body only tells which payout the
 * channel is talking about, and the outcome is asked for with a query of its own afterwards. That is
 * why the interface is so small - it either proves that the body was sent by the channel, or it does
 * not, and then it names the payout.
 *
 * The credentials are handed in instead of being loaded here: which merchant account signs a payout
 * notification depends on the app the notification was addressed to, and a verifier that looked them
 * up itself would have to know that app. They are the decrypted credentials of the interface, the
 * same array the gateway actions read.
 *
 * Failure semantics matter for the retry behaviour of the channels:
 *
 *  - a body that was not signed by the channel is `false` and nothing else. It is the normal answer to
 *    a forged, truncated or otherwise broken notification, and it must not abort the request;
 *  - a missing credential, an unreadable platform certificate or a body that cannot be decrypted is an
 *    error of this installation and throws. The endpoint answers those with a 5xx so the channel sends
 *    the notification again instead of the platform losing it.
 *
 * @internal
 */
#[Package('framework')]
interface PaymentTransferNotificationVerifierInterface
{
    /**
     * The technical name of the interface this verifier speaks for, as used in the notify url.
     */
    public function provider(): string;

    /**
     * Whether the body was signed by the interface.
     *
     * @param array<string, mixed> $credentials the decrypted merchant credentials of the interface
     * @param array<string, string|list<string>> $headers
     */
    public function verify(array $credentials, string $rawBody, array $headers): bool;

    /**
     * The payout number the signed body carries, or null when the body does not name one.
     *
     * Only called after {@see verify()} returned true - the body is untrusted before that.
     *
     * @param array<string, mixed> $credentials the decrypted merchant credentials of the interface
     * @param array<string, string|list<string>> $headers
     */
    public function outNoOf(array $credentials, string $rawBody, array $headers): ?string;

    /**
     * The answer this interface expects, so that it does not repeat the notification forever.
     */
    public function acknowledge(): PaymentNotificationAck;
}
