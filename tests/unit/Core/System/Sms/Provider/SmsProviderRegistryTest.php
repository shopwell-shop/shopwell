<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Shopwell\Core\System\Sms\SmsException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsProviderRegistry::class)]
class SmsProviderRegistryTest extends TestCase
{
    public function testRegistersAPluginProvider(): void
    {
        $provider = static::createStub(AbstractSmsProvider::class);
        $provider->method('getTechnicalName')->willReturn('plugin');

        $registry = new SmsProviderRegistry([$provider]);

        static::assertSame($provider, $registry->get('plugin'));
    }

    public function testRejectsDuplicateProviderDeclarations(): void
    {
        $provider = static::createStub(AbstractSmsProvider::class);
        $provider->method('getTechnicalName')->willReturn('plugin');

        $this->expectExceptionObject(SmsException::invalidProviderDeclaration('plugin'));

        new SmsProviderRegistry([$provider, $provider]);
    }

    public function testRejectsAnEmptyProviderName(): void
    {
        $provider = static::createStub(AbstractSmsProvider::class);
        $provider->method('getTechnicalName')->willReturn('');
        $this->expectExceptionObject(SmsException::invalidProviderDeclaration(''));
        new SmsProviderRegistry([$provider]);
    }
}
