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
 * The transport a provider builds reports a failure as SmsException. A failure that is worth
 * trying again - the provider was unreachable, it answered with a rate limit - is thrown as that
 * class, a failure that will not pass on a second attempt - the credentials are wrong, the
 * provider rejected the template - is thrown as its SmsUnrecoverableException subclass, which the
 * send handler turns into a message that is not retried. The factories of the exception pair the
 * two, so a transport picks the factory and the retry behaviour follows.
 *
 * Whether the provider wants its templates reported before they are usable is a separate,
 * optional contract next to this one - see Gateway\Audit.
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
