<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Integration\Aliyun\AliyunSmsProvider;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigField;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigValidator;
use Shopwell\Core\System\Sms\SmsException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsConfigValidator::class)]
class SmsConfigValidatorTest extends TestCase
{
    public function testRejectsBlankCredentialsWithoutDisclosingTheirValues(): void
    {
        $this->expectExceptionObject(SmsException::configurationInvalid('aliyun', ['credentials.accessKeySecret']));

        (new SmsConfigValidator())->validate(new AliyunSmsProvider(), ['accessKeyId' => 'key', 'accessKeySecret' => '  '], []);
    }

    public function testRequiresPersistedCredentialsEvenWhenTheFormDeclaresADefault(): void
    {
        $provider = static::createStub(AbstractSmsProvider::class);
        $provider->method('getTechnicalName')->willReturn('plugin');
        $provider->method('getCredentialDefinitions')->willReturn([
            (new SmsConfigField('region', 'plugin.region', required: true, default: 'us'))->toArray(),
        ]);
        $this->expectExceptionObject(SmsException::configurationInvalid('plugin', ['credentials.region']));
        (new SmsConfigValidator())->validate($provider, [], []);
    }

    public function testKeepsUnknownOptionsAvailableToTheProvider(): void
    {
        (new SmsConfigValidator())->validate(
            new AliyunSmsProvider(),
            ['accessKeyId' => 'key', 'accessKeySecret' => 'secret'],
            ['signName' => 'Shop', 'pluginOption' => ['custom' => true]],
        );

        $this->expectNotToPerformAssertions();
    }
}
