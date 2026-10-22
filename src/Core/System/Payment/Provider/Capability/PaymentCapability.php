<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Capability;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Refund;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;

/**
 * One thing a payment interface can do, named explicitly instead of being derived from the classes it
 * happens to ship.
 *
 * The capabilities come from two different sources on purpose, so that there is never a second truth
 * for the same capability:
 *
 *  - **request capabilities** (`capture`, `query`, `close`, `refund`, `transfer`, `transfer_query`,
 *    `notification`) exist because an action of the interface declares them through
 *    `AbstractPaymentAction::capability()`. Declaring one of them anywhere else would announce a
 *    capability no code executes, which is why the declaration checker rejects that.
 *  - **policy capabilities** (`partial_refund`, `mandate`, `transfer_notification`) have no request of
 *    their own and are therefore declared by the interface through
 *    `AbstractPaymentProvider::getDeclaredCapabilities()`.
 *
 * Adding another case is an additive change: consumers answer "can this interface do X" with
 * `PaymentProvider::supports()` and an unknown case simply answers `false` until the code that
 * executes it exists.
 */
#[Package('framework')]
enum PaymentCapability: string
{
    /**
     * The interface opens a payment - the one capability every interface has to offer.
     */
    case CAPTURE = 'capture';

    /**
     * The interface answers what became of a payment, which is what the reconciliation asks for.
     */
    case QUERY = 'query';

    /**
     * The interface can revoke a payment that was not paid yet.
     */
    case CLOSE = 'close';

    /**
     * The interface can give money back.
     */
    case REFUND = 'refund';

    /**
     * The interface can pay out to a recipient of the merchant.
     */
    case TRANSFER = 'transfer';

    /**
     * The interface answers what became of a payout.
     */
    case TRANSFER_QUERY = 'transfer_query';

    /**
     * The interface reports a payment asynchronously, so the platform has to accept its callbacks.
     */
    case NOTIFICATION = 'notification';

    /**
     * The interface refunds less than the full amount of a payment.
     */
    case PARTIAL_REFUND = 'partial_refund';

    /**
     * The interface can charge a payer repeatedly on an agreement signed once.
     */
    case MANDATE = 'mandate';

    /**
     * The interface reports a payout asynchronously.
     */
    case TRANSFER_NOTIFICATION = 'transfer_notification';

    /**
     * The payum request that carries this capability, or null for a capability without a request.
     *
     * This is the reverse of `AbstractPaymentAction::capability()`: a service that knows the
     * capability it needs asks for the request it has to send, without knowing the class names of the
     * interface that implements it.
     *
     * @return class-string|null
     */
    public function requestClass(): ?string
    {
        return match ($this) {
            self::CAPTURE => Capture::class,
            self::QUERY => PaymentQuery::class,
            self::CLOSE => PaymentClose::class,
            self::REFUND => Refund::class,
            self::TRANSFER => PaymentTransferRequest::class,
            self::TRANSFER_QUERY => PaymentTransferQuery::class,
            self::NOTIFICATION => Notify::class,
            self::PARTIAL_REFUND, self::MANDATE, self::TRANSFER_NOTIFICATION => null,
        };
    }

    /**
     * Whether the capability is backed by an action of the interface instead of by a declaration.
     *
     * Request capabilities may not be declared - they are read off the actions - and policy
     * capabilities have no action that could carry them.
     */
    public function isRequestCapability(): bool
    {
        return $this->requestClass() !== null;
    }
}
