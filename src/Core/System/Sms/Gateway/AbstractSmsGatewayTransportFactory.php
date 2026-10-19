<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * The base of a provider of the platform.
 *
 * It holds what every provider shares: the fields it declares in its credential schema are checked
 * before a client is built, so a gateway that was stored without one of them reports a configuration
 * the shop has to fix rather than a request the provider refused. The administration renders the same
 * schema into the form that fills the credentials, so the two can never disagree.
 */
#[Package('framework')]
abstract class AbstractSmsGatewayTransportFactory implements SmsGatewayTransportFactoryInterface
{
    /**
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     *
     * @throws SmsException a configuration the shop has to fix, thrown as the unrecoverable subclass
     */
    final protected function requireCredentials(array $credentials): void
    {
        $missing = [];

        foreach ($this->getCredentialDefinitions() as $definition) {
            if (!$definition['required']) {
                continue;
            }

            if (($credentials[$definition['name']] ?? null) === null) {
                $missing[] = $definition['name'];
            }
        }

        if ($missing !== []) {
            throw SmsException::credentialsIncomplete($this->getTechnicalName(), $missing);
        }
    }
}
