<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * A payment interface: the merchant account an app collects through, and everything that account can
 * do.
 *
 * This is the **one contract a plugin has to implement** to add a payment interface, a channel of an
 * existing interface or another implementation for a single step of one. Nothing else has to be
 * touched: no core file, no administration file, no database table. Declaring the service twice -
 * once as the implementation, once as the tag - is the whole registration:
 *
 * ```php
 * $services->set(MyProvider::class)
 *     ->args([service(MyCaptureAction::class)])
 *     ->tag('shopwell.payment.provider', ['key' => 'mypay', 'priority' => 0]);
 * ```
 *
 * The `key` of the tag is the technical name the interface is addressed by: it appears in the notify
 * url (`/payment-api/v1/notify/mypay/…`), in `payment_order.provider_technical_name`, in
 * `payment_provider_config` and in `payment_app_provider`. It therefore has to match
 * `[A-Za-z0-9_-]+` and it may never be reused for a different implementation - historic rows would
 * become uninterpretable. `priority` orders several implementations of the *same* key: the lowest
 * priority is the base implementation (the core interfaces use `-100`) and every other one has to be a
 * decorator that returns the next one in `getDecorated()`. Two implementations of one key where the
 * second is not a decorator fail the container compilation.
 *
 * An implementation has to be stateless and may not add public methods beyond the ones declared here:
 * a decorator can only forward what is part of this contract, and the promotion from "works by
 * accident" to "supported" is what the whole abstraction is for.
 *
 * What an interface *can do* is not derived from the classes it happens to ship. A request capability
 * (`capture`, `query`, `close`, `refund`, `transfer`, `transfer_query`, `notification`) exists because
 * one of its actions declares it in `AbstractPaymentAction::capability()`; a policy capability
 * (`partial_refund`, `mandate`, `transfer_notification`) is declared explicitly in
 * {@see self::getDeclaredCapabilities()}. Declaring a request capability without an action behind it is
 * rejected, because it would announce something no code executes.
 */
#[Package('framework')]
abstract class AbstractPaymentProvider
{
    /**
     * The technical name of the interface, e.g. `alipay`.
     *
     * It has to match `[A-Za-z0-9_-]+` because it is part of the notify url, and it is the value the
     * payment orders, the provider configuration and the app authorization store. It never changes.
     */
    abstract public function getName(): string;

    /**
     * The **snippet key** of the name the administration shows, not the name itself.
     *
     * A key - e.g. `sw-settings-payment-provider.provider.alipay` - is what makes the interface
     * translatable and what lets a plugin ship its own wording in its own admin snippet. The key is
     * resolved by the administration; an installation in a language the key has no entry for shows the
     * key, which is ugly but honest.
     */
    abstract public function getLabel(): string;

    /**
     * The api of the interface: the object the actions talk to.
     *
     * It is built per payment, because it carries the resolved credentials of the very payment - the
     * merchant account of the app or the one of the platform.
     *
     * @return class-string<AbstractPaymentApi>
     */
    abstract public function getApiClass(): string;

    /**
     * Every action the interface consists of, one per capability it implements.
     *
     * The gateway of a payment is assembled out of these, and payum picks the action whose `supports()`
     * answers for the request. Two actions for the same capability are allowed - a capture per channel
     * is the normal case - and the action decides by the request and by `AbstractPaymentApi::channel()`
     * whether it is responsible.
     *
     * @return list<AbstractPaymentAction>
     */
    abstract public function getActions(): array;

    /**
     * The action that opens a payment for a channel that has no more specific one.
     *
     * @see self::getChannelCaptureActions() for the per channel override
     */
    abstract public function getCaptureAction(): AbstractPaymentAction;

    /**
     * The service that reads the references out of an incoming notification.
     *
     * It has to work without credentials: the platform needs to know which payment order a notification
     * belongs to *before* it can look up the credentials needed to verify it.
     */
    abstract public function getNotificationExtractor(): PaymentNotificationExtractorInterface;

    /**
     * The **snippet key** of the longer description the administration shows, or null for none.
     */
    public function getDescription(): ?string
    {
        return null;
    }

    /**
     * The fields of the merchant account, in the order the administration renders them.
     *
     * Every channel of the interface is charged on this very account, which is why the credentials live
     * here and not on the channel. A field marked secret is never echoed back to the administration.
     *
     * @return list<PaymentConfigField>
     */
    public function getCredentials(): array
    {
        return [];
    }

    /**
     * The ways of paying this interface offers, in the order the administration renders them.
     *
     * A channel is what an app names when it wants to be paid (`alipay_h5`, `wechat_native`, …), so its
     * technical name is unique across **all** interfaces - two interfaces offering the same name would
     * leave the platform unable to tell which merchant account to charge.
     *
     * @return list<PaymentChannel>
     */
    public function getChannels(): array
    {
        return [];
    }

    /**
     * The capture action of a single channel, for an interface whose channels open differently enough
     * that one action cannot serve them all.
     *
     * A channel that is not named here falls back to {@see self::getCaptureAction()}.
     *
     * @return array<string, AbstractPaymentAction> channel technical name => action
     */
    public function getChannelCaptureActions(): array
    {
        return [];
    }

    /**
     * The services the api needs besides the payment itself, in constructor order.
     *
     * An interface that fetches a certificate, for example, returns its http client here.
     *
     * @return list<mixed>
     */
    public function getApiArguments(): array
    {
        return [];
    }

    /**
     * The decoder that turns the recorded answer of the last capture back into payer parameters.
     *
     * A payer who comes back, reloads or switches the device has to be handed to the answer the channel
     * already gave - a second capture could open a second trade. Only the interface knows the shape of
     * its own answer, so the decoding belongs here. Null means "capture again", which is correct but
     * costs a second call at the channel.
     */
    public function getPayParamsDecoder(): ?PayParamsDecoderInterface
    {
        return null;
    }

    /**
     * The capabilities that have no action of their own.
     *
     * Only `partial_refund`, `mandate` and `transfer_notification` belong here. A request capability
     * listed here is rejected by the declaration checker: nothing would execute it.
     *
     * @return list<PaymentCapability>
     */
    public function getDeclaredCapabilities(): array
    {
        return [];
    }

    /**
     * What the interface demands before it pays out to a recipient, or null if it cannot pay out.
     */
    public function getTransferRules(): ?PaymentTransferRules
    {
        return null;
    }

    /**
     * What the interface demands from a merchant-initiated recurring charge, or null if it cannot be
     * charged repeatedly.
     */
    public function getCycleRules(): ?PaymentCycleRules
    {
        return null;
    }

    /**
     * The service that verifies an incoming payout notification of this interface.
     *
     * It exists exactly when the interface declares {@see PaymentCapability::TRANSFER_NOTIFICATION} -
     * the declaration and the implementation are checked against each other.
     */
    public function getTransferNotificationVerifier(): ?PaymentTransferNotificationVerifierInterface
    {
        return null;
    }

    /**
     * The name of the plugin that ships this interface, for the administration only.
     *
     * Core interfaces answer null; a plugin answers its own name so an operator can tell where an
     * interface comes from before disabling the plugin that provides it.
     */
    public function getSourceName(): ?string
    {
        return null;
    }

    /**
     * The next implementation of this interface in the decoration chain.
     *
     * A decorator returns the instance it was built with; the base implementation does not implement
     * this method at all and keeps the one below, which throws.
     *
     * @throws DecorationPatternException when called on an implementation that is not a decorator
     */
    public function getDecorated(): AbstractPaymentProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
