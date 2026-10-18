<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The single contract a provider has to fulfil to be usable as an sms gateway.
 *
 * A provider of the platform is one implementation of this interface registered with the
 * `shopwell.sms.gateway` tag, an extension brings its own in the services of the extension - the
 * administration renders the credential form from getCredentialDefinitions() and the routing and
 * the bookkeeping have nothing provider specific, so adding a provider touches no core code.
 *
 * @internal
 */
#[Package('framework')]
interface SmsGatewayTransportFactoryInterface
{
    /**
     * The technical name the sms_gateway rows and the template mappings refer to, e.g. aliyun.
     */
    public function getTechnicalName(): string;

    /**
     * The snippet key of the name shown in the administration, e.g. sms.gateway.aliyun.
     */
    public function getLabelSnippet(): string;

    /**
     * The regions the gateway can deliver to, e.g. ['CN'] or ['GLOBAL'], used to route by the
     * country of the recipient.
     *
     * @return list<string>
     */
    public function getSupportedRegions(): array;

    /**
     * Whether the provider sends templates reported at its side only - true for the Chinese
     * providers, false for the ones that take a free text body.
     */
    public function supportsTemplateMode(): bool;

    /**
     * The credential fields of the provider, rendered by the administration and validated before a
     * gateway is stored. A field of the type secret is rendered as a password input, the value of a
     * credential is data of the shop and is read and written like any other row.
     *
     * @return list<array{name: string, type: 'string'|'secret'|'json'|'bool', required: bool, labelSnippet: string}>
     */
    public function getCredentialDefinitions(): array;

    /**
     * Builds the transport of one gateway row.
     *
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     * @param array<string, mixed> $options the connection options of the sms_gateway row
     */
    public function create(
        array $credentials,
        array $options,
        ?EventDispatcherInterface $dispatcher = null,
    ): TransportInterface;
}
