<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Integration\Null\NullSmsTransport;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigField;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractSmsProvider::class)]
class AbstractSmsProviderTest extends TestCase
{
    public function testMinimalProviderCanSendWithoutTemplateApproval(): void
    {
        $provider = new class extends AbstractSmsProvider {
            public function getTechnicalName(): string
            {
                return 'plugin';
            }

            public function getLabelSnippet(): string
            {
                return 'plugin.sms.label';
            }

            public function getCredentials(): array
            {
                return [new SmsConfigField('token', 'plugin.sms.token', type: 'secret', required: true)];
            }

            public function createTransport(array $credentials, array $options, ?EventDispatcherInterface $dispatcher = null): TransportInterface
            {
                return new NullSmsTransport($dispatcher);
            }
        };

        static::assertFalse($provider->supportsTemplateMode());
        static::assertSame(['GLOBAL'], $provider->getSupportedRegions());
        static::assertSame([], $provider->getOptionDefinitions());
        static::assertSame([
            ['name' => 'token', 'type' => 'secret', 'required' => true, 'labelSnippet' => 'plugin.sms.token'],
        ], $provider->getCredentialDefinitions());
        static::assertSame('null', (string) $provider->createTransport(['token' => 'secret'], []));
    }
}
