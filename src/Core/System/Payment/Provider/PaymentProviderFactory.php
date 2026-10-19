<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;

/**
 * Turns the declaration of an interface into the immutable snapshot the platform works with.
 *
 * The conversion is where a declaration is actually checked. It happens once per interface, on the
 * first use of the registry, and it is deliberately strict: every one of these checks used to be
 * something a developer had to remember, and each of them fails in a way that is invisible until
 * money is involved.
 *
 *  - the technical name has to match `[A-Za-z0-9_-]+`, because the notify url carries it;
 *  - the technical name has to be the key the service was tagged with, because the container and the
 *    registry address the interface by that key while every stored row uses the name;
 *  - a credential field may not be declared twice, and may not be nameless - the stored credentials
 *    are a json object keyed by that name, so a duplicate silently overwrites;
 *  - a channel may not be nameless, may not be declared twice by one interface and may not claim to
 *    belong to another interface - the channel name is how an app addresses a payment;
 *  - `CAPTURE` has to be part of the capability set, otherwise the interface cannot collect at all;
 *  - a request capability has to be backed by an action that declares it, because a capability without
 *    code is an announcement nobody keeps;
 *  - `TRANSFER_NOTIFICATION` and the payout notification verifier imply each other: a verifier that is
 *    not declared is dead code, a declaration without a verifier means unverified bodies are trusted;
 *  - a decorator may not drop a credential field of the interface it decorates - the stored
 *    credentials are keyed by field name, so a dropped field is a silently lost merchant account.
 *
 * The last one is the reason the chain is walked here: the outermost declaration is the only one the
 * container hands over, so the inner ones are reached through `getDecorated()`.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderFactory
{
    /**
     * The technical names the notify url can carry, see `Api/NotifyController`.
     */
    private const NAME_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * A declaration that decorates itself would make the chain walk below endless.
     */
    private const MAX_DECORATION_DEPTH = 32;

    /**
     * @param string|null $expectedName the technical name the service was tagged with, when the caller
     *                                  already knows it - it has to be the name the declaration gives
     *                                  itself, see the check below
     *
     * @throws PaymentException
     */
    public function create(AbstractPaymentProvider $declaration, ?string $expectedName = null): PaymentProvider
    {
        $name = $declaration->getName();

        // The tag key is what the container, the registry and the notify url address the interface by,
        // while every stored row is keyed by the name the declaration gives itself. A declaration whose
        // key and name disagree would therefore be configured under one name and paid under the other,
        // so the two are compared before anything is built out of the object.
        if ($expectedName !== null && $expectedName !== $name) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The declaration "%s" is tagged as the payment interface "%s" but names itself "%s". The tag key and the technical name have to be the same.',
                $declaration::class,
                $expectedName,
                $name
            ));
        }

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw PaymentException::invalidProviderName($name);
        }

        $credentials = $this->credentialFieldsOf($declaration, $name);
        $channels = $this->channelsOf($declaration, $name);
        $actions = $this->actionsOf($declaration, $name);
        $capabilities = $this->capabilitiesOf($declaration, $actions, $name);

        // the chain is walked once: it is where a decorator that renamed the interface is refused, and
        // the credential rule below is the second thing that needs the whole chain
        $declarations = $this->decorationChainOf($declaration, $name);
        $this->assertDecoratorsKeepTheCredentials($declarations, $name);

        $apiClass = $declaration->getApiClass();

        if (!is_a($apiClass, AbstractPaymentApi::class, true)) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The payment interface "%s" names the api "%s", which does not extend %s.',
                $name,
                $apiClass,
                AbstractPaymentApi::class
            ));
        }

        return new PaymentProvider(
            technicalName: $name,
            label: $declaration->getLabel(),
            credentials: $credentials,
            channels: $channels,
            gatewayFactory: new PaymentGatewayFactory($name, $apiClass, $actions, array_values($declaration->getApiArguments())),
            notificationExtractor: $declaration->getNotificationExtractor(),
            payParamsDecoder: $declaration->getPayParamsDecoder(),
            capabilities: $capabilities,
        );
    }

    /**
     * @throws PaymentException
     *
     * @return list<PaymentConfigField>
     */
    private function credentialFieldsOf(AbstractPaymentProvider $declaration, string $name): array
    {
        $fields = [];
        $seen = [];

        foreach ($declaration->getCredentials() as $field) {
            if (!$field instanceof PaymentConfigField) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares a credential that is not a %s.',
                    $name,
                    PaymentConfigField::class
                ));
            }

            if ($field->name === '') {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares a configuration field without a name.',
                    $name
                ));
            }

            if (isset($seen[$field->name])) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares the configuration field "%s" twice.',
                    $name,
                    $field->name
                ));
            }

            $seen[$field->name] = true;
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @throws PaymentException
     *
     * @return array<string, PaymentChannel>
     */
    private function channelsOf(AbstractPaymentProvider $declaration, string $name): array
    {
        $channels = [];

        foreach ($declaration->getChannels() as $channel) {
            if (!$channel instanceof PaymentChannel) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares a channel that is not a %s.',
                    $name,
                    PaymentChannel::class
                ));
            }

            if ($channel->technicalName === '') {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares a channel without a name.',
                    $name
                ));
            }

            if (isset($channels[$channel->technicalName])) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The channel "%s" is declared twice by the payment interface "%s".',
                    $channel->technicalName,
                    $name
                ));
            }

            if ($channel->providerTechnicalName !== $name) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The channel "%s" claims to belong to the payment interface "%s", but it is declared by "%s".',
                    $channel->technicalName,
                    $channel->providerTechnicalName,
                    $name
                ));
            }

            $channels[$channel->technicalName] = $channel;
        }

        return $channels;
    }

    /**
     * Every action of the interface, plus the ones that only serve a single channel.
     *
     * The channel capture actions are ordinary actions: they answer `supports()` for the channel they
     * were written for and are ignored for every other one, which is how a plugin adds a channel to an
     * existing interface without the core learning about it.
     *
     * @throws PaymentException
     *
     * @return list<AbstractPaymentAction>
     */
    private function actionsOf(AbstractPaymentProvider $declaration, string $name): array
    {
        $actions = [];

        foreach ($declaration->getActions() as $action) {
            if (!$action instanceof AbstractPaymentAction) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares an action that is not a %s.',
                    $name,
                    AbstractPaymentAction::class
                ));
            }

            if (!\in_array($action, $actions, true)) {
                $actions[] = $action;
            }
        }

        foreach ($declaration->getChannelCaptureActions() as $channel => $action) {
            if (!$action instanceof AbstractPaymentAction) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" names an action for the channel "%s" that is not a %s.',
                    $name,
                    (string) $channel,
                    AbstractPaymentAction::class
                ));
            }

            if (!\in_array($action, $actions, true)) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * The union of what the actions declare and what the declaration declares itself, checked for
     * coherence.
     *
     * @param list<AbstractPaymentAction> $actions
     *
     * @throws PaymentException
     *
     * @return list<PaymentCapability>
     */
    private function capabilitiesOf(AbstractPaymentProvider $declaration, array $actions, string $name): array
    {
        $fromActions = [];
        $fromDeclaration = [];

        foreach ($actions as $action) {
            $capability = $action->capability();

            if (!$capability->isRequestCapability()) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The action "%s" of the payment interface "%s" declares the capability "%s", which has no request - declare it in getDeclaredCapabilities() instead.',
                    $action::class,
                    $name,
                    $capability->value
                ));
            }

            $fromActions[$capability->value] = $capability;
        }

        foreach ($declaration->getDeclaredCapabilities() as $capability) {
            if (!$capability instanceof PaymentCapability) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares a capability that is not a %s.',
                    $name,
                    PaymentCapability::class
                ));
            }

            if ($capability->isRequestCapability()) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The payment interface "%s" declares the capability "%s", which is backed by an action - a request capability cannot be declared, it is read off the actions.',
                    $name,
                    $capability->value
                ));
            }

            $fromDeclaration[$capability->value] = $capability;
        }

        $capabilities = [...array_values($fromActions), ...array_values(array_diff_key($fromDeclaration, $fromActions))];

        if (!isset($fromActions[PaymentCapability::CAPTURE->value])) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The payment interface "%s" declares no capture action, so it cannot collect a payment at all.',
                $name
            ));
        }

        $verifier = $declaration->getTransferNotificationVerifier();
        $declaresTransferNotification = \in_array(PaymentCapability::TRANSFER_NOTIFICATION, $capabilities, true);

        if ($declaresTransferNotification && $verifier === null) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The payment interface "%s" declares the capability "%s" but no transfer notification verifier, so it would have to trust unverified payout notifications.',
                $name,
                PaymentCapability::TRANSFER_NOTIFICATION->value
            ));
        }

        if (!$declaresTransferNotification && $verifier !== null) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The payment interface "%s" ships a transfer notification verifier but does not declare the capability "%s", so the verifier can never be reached.',
                $name,
                PaymentCapability::TRANSFER_NOTIFICATION->value
            ));
        }

        return $capabilities;
    }

    /**
     * A decorator may add a credential field, but not remove one.
     *
     * `payment_provider_config.credentials` is a json object keyed by field name, so a field a
     * decorator forgot to forward is not a cosmetic defect: the stored value of that field becomes
     * unreachable and the merchant account quietly stops working.
     *
     * @param list<AbstractPaymentProvider> $declarations the chain, outermost first
     *
     * @throws PaymentException
     */
    private function assertDecoratorsKeepTheCredentials(array $declarations, string $name): void
    {
        for ($i = 0, $count = \count($declarations); $i < $count - 1; ++$i) {
            $inner = $declarations[$i + 1]->getCredentials();
            $outerNames = array_map(
                static fn (PaymentConfigField $field): string => $field->name,
                $declarations[$i]->getCredentials()
            );

            foreach ($inner as $field) {
                if (\in_array($field->name, $outerNames, true)) {
                    continue;
                }

                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The decoration "%s" of the payment interface "%s" no longer declares the credential field "%s" of the declaration it decorates - a stored field has to stay reachable.',
                    $declarations[$i]::class,
                    $name,
                    $field->name
                ));
            }
        }
    }

    /**
     * The outermost declaration first, the base implementation last.
     *
     * `getDecorated()` throws on the base implementation, which is the end of the chain - it is not an
     * error here, it is the answer.
     *
     * @throws PaymentException
     *
     * @return list<AbstractPaymentProvider>
     */
    private function decorationChainOf(AbstractPaymentProvider $declaration, string $name): array
    {
        $chain = [];
        $current = $declaration;

        while (true) {
            $chain[] = $current;

            if (\count($chain) > self::MAX_DECORATION_DEPTH) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The decoration chain of the payment interface "%s" is deeper than %d entries, which means a declaration decorates itself.',
                    $name,
                    self::MAX_DECORATION_DEPTH
                ));
            }

            try {
                $current = $current->getDecorated();
            } catch (DecorationPatternException) {
                return $chain;
            }

            if ($current->getName() !== $name) {
                throw PaymentException::invalidProviderDeclaration(\sprintf(
                    'The decoration "%s" of the payment interface "%s" decorates "%s" - a decorator has to keep the technical name of the declaration it decorates.',
                    $chain[0]::class,
                    $name,
                    $current->getName()
                ));
            }
        }
    }
}
