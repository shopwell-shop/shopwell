<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProviderDefinition;
use Shopwell\Core\System\Payment\Provider\PaymentProviderSchemaReader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderSchemaReader::class)]
class PaymentProviderSchemaReaderTest extends TestCase
{
    private const ALIPAY_DECLARATION = __DIR__ . '/../../../../../../src/Core/System/Payment/Provider/Resources/config/alipay.xml';

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testReadsTheNameAndLabelOfTheDeclaration(): void
    {
        $definition = $this->reader()->readProvider(self::ALIPAY_DECLARATION);

        static::assertSame('alipay', $definition->technicalName);
        static::assertSame('支付宝', $definition->label);
    }

    public function testReadsTheCredentialFieldsOfTheDeclaration(): void
    {
        $definition = $this->reader()->readProvider(self::ALIPAY_DECLARATION);

        static::assertCount(6, $definition->credentials);
        static::assertSame(
            ['app_id', 'sandbox', 'private_key', 'alipay_public_key', 'sign_type', 'gateway_url'],
            array_map(static fn (PaymentConfigField $field): string => $field->name, $definition->credentials)
        );
    }

    public function testReadsATextCredential(): void
    {
        $field = $this->reader()->readProvider(self::ALIPAY_DECLARATION)->credentialField('app_id');

        static::assertInstanceOf(PaymentConfigField::class, $field);
        static::assertSame('支付宝应用 APPID', $field->label);
        static::assertSame(PaymentConfigField::TYPE_TEXT, $field->type);
        static::assertTrue($field->required);
        static::assertFalse($field->secret);
        static::assertSame([], $field->options);
        static::assertNull($field->default);
        static::assertNull($field->helpText);
    }

    public function testReadsAMultiLineSecretCredential(): void
    {
        $field = $this->reader()->readProvider(self::ALIPAY_DECLARATION)->credentialField('private_key');

        static::assertInstanceOf(PaymentConfigField::class, $field);
        static::assertSame(PaymentConfigField::TYPE_TEXTAREA, $field->type);
        static::assertTrue($field->required);
        static::assertTrue($field->secret);
    }

    public function testReadsABoolCredentialWithItsDefault(): void
    {
        $field = $this->reader()->readProvider(self::ALIPAY_DECLARATION)->credentialField('sandbox');

        static::assertInstanceOf(PaymentConfigField::class, $field);
        static::assertSame(PaymentConfigField::TYPE_BOOL, $field->type);
        static::assertFalse($field->required);
        static::assertSame('0', $field->default);
    }

    public function testReadsASelectCredentialWithItsOptions(): void
    {
        $field = $this->reader()->readProvider(self::ALIPAY_DECLARATION)->credentialField('sign_type');

        static::assertInstanceOf(PaymentConfigField::class, $field);
        static::assertTrue($field->isSelect());
        static::assertSame(['RSA2' => 'RSA2', 'RSA' => 'RSA'], $field->options);
        static::assertSame('RSA2', $field->default);
    }

    public function testReadsTheHelpTextOfACredential(): void
    {
        $field = $this->reader()->readProvider(self::ALIPAY_DECLARATION)->credentialField('gateway_url');

        static::assertInstanceOf(PaymentConfigField::class, $field);
        static::assertSame('https://openapi.alipay.com', $field->default);
        static::assertNotNull($field->helpText);
    }

    public function testReadsTheChannelsOfTheDeclaration(): void
    {
        $definition = $this->reader()->readProvider(self::ALIPAY_DECLARATION);

        static::assertCount(5, $definition->channels);
        static::assertTrue($definition->hasChannel('alipay_h5'));
        static::assertTrue($definition->hasChannel('alipay_web'));
        static::assertTrue($definition->hasChannel('alipay_qr'));
        static::assertTrue($definition->hasChannel('alipay_app'));
        static::assertTrue($definition->hasChannel('alipay_mini'));
        static::assertFalse($definition->hasChannel('unknown'));
    }

    public function testReadsTheFlowCurrenciesAndDevicesOfAChannel(): void
    {
        $definition = $this->reader()->readProvider(self::ALIPAY_DECLARATION);

        $h5 = $this->channelOf($definition, 'alipay_h5');

        static::assertSame('alipay', $h5->providerName);
        static::assertSame('支付宝 手机网站支付', $h5->label);
        static::assertSame(PaymentFlow::REDIRECT, $h5->flow);
        static::assertSame(['CNY'], $h5->currencies);
        static::assertSame(['wap'], $h5->devices);
        static::assertSame([], $h5->params);
        static::assertSame([], $h5->runtimeParams);
    }

    public function testReadsACommaSeparatedDeviceListOfAChannel(): void
    {
        $definition = $this->reader()->readProvider(self::ALIPAY_DECLARATION);

        $qr = $this->channelOf($definition, 'alipay_qr');

        static::assertSame(PaymentFlow::QRCODE, $qr->flow);
        static::assertSame(['web', 'wap'], $qr->devices);
    }

    public function testReadsTheRuntimeParametersOfAChannel(): void
    {
        $definition = $this->reader()->readProvider(self::ALIPAY_DECLARATION);

        $mini = $this->channelOf($definition, 'alipay_mini');

        static::assertSame(PaymentFlow::SDK, $mini->flow);
        static::assertSame(['buyer_id' => true], $mini->runtimeParams);
        static::assertSame(['buyer_id'], $mini->requiredRuntimeParams());
        $mini->validateRuntimeParams(['buyer_id' => '2088']);
    }

    public function testNormalisesTheCurrenciesAndKeepsTheEmptyDeviceList(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
                <label>Demo</label>
                <credentials>
                    <field name="api_key" type="password" required="true" secret="true" default="x">
                        <label>API Key</label>
                        <help-text>the secret key</help-text>
                    </field>
                    <field name="mode" type="select">
                        <label>Mode</label>
                        <option value="live">Live</option>
                        <option value="test"> Test </option>
                    </field>
                </credentials>
                <channels>
                    <channel name="demo_card">
                        <label>Card</label>
                        <flow>redirect</flow>
                        <currencies>cny, usd , cny</currencies>
                        <devices></devices>
                        <params>
                            <field name="brand" type="text" required="true">
                                <label>Brand</label>
                            </field>
                        </params>
                    </channel>
                </channels>
            </provider>
            XML);

        $definition = $this->reader()->readProvider($file);

        static::assertSame('demo', $definition->technicalName);

        $apiKey = $definition->credentialField('api_key');
        static::assertInstanceOf(PaymentConfigField::class, $apiKey);
        static::assertSame(PaymentConfigField::TYPE_PASSWORD, $apiKey->type);
        static::assertTrue($apiKey->required);
        static::assertTrue($apiKey->secret);
        static::assertSame('x', $apiKey->default);
        static::assertSame('the secret key', $apiKey->helpText);

        $mode = $definition->credentialField('mode');
        static::assertInstanceOf(PaymentConfigField::class, $mode);
        static::assertSame(['live' => 'Live', 'test' => 'Test'], $mode->options);
        static::assertNull($mode->default);

        $card = $this->channelOf($definition, 'demo_card');
        static::assertSame(['CNY', 'USD'], $card->currencies);
        static::assertSame([], $card->devices);
        static::assertCount(1, $card->params);
        static::assertSame('brand', $card->params[0]->name);
        static::assertTrue($card->params[0]->required);
    }

    public function testRejectsADeclarationWithAnUnknownFlow(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
                <label>Demo</label>
                <channels>
                    <channel name="demo_card">
                        <label>Card</label>
                        <flow>telepathy</flow>
                    </channel>
                </channels>
            </provider>
            XML);

        $this->expectException(PaymentException::class);

        $this->reader()->readProvider($file);
    }

    public function testRejectsAFieldWithoutAName(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
                <label>Demo</label>
                <credentials>
                    <field name=""><label>Nameless</label></field>
                </credentials>
                <channels>
                    <channel name="demo_card">
                        <label>Card</label>
                        <flow>redirect</flow>
                    </channel>
                </channels>
            </provider>
            XML);

        $this->expectException(PaymentException::class);

        $this->reader()->readProvider($file);
    }

    public function testRejectsADeclarationThatNamesAFieldTwice(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
                <label>Demo</label>
                <credentials>
                    <field name="api_key"><label>One</label></field>
                    <field name="api_key"><label>Two</label></field>
                </credentials>
                <channels>
                    <channel name="demo_card">
                        <label>Card</label>
                        <flow>redirect</flow>
                    </channel>
                </channels>
            </provider>
            XML);

        $this->expectException(PaymentException::class);

        $this->reader()->readProvider($file);
    }

    public function testRejectsAChannelWithoutAName(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
                <label>Demo</label>
                <channels>
                    <channel name="">
                        <label>Card</label>
                        <flow>redirect</flow>
                    </channel>
                </channels>
            </provider>
            XML);

        $this->expectException(PaymentException::class);

        $this->reader()->readProvider($file);
    }

    public function testRejectsADeclarationThatNamesAChannelTwice(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
                <label>Demo</label>
                <channels>
                    <channel name="demo_card">
                        <label>One</label>
                        <flow>redirect</flow>
                    </channel>
                    <channel name="demo_card">
                        <label>Two</label>
                        <flow>qrcode</flow>
                    </channel>
                </channels>
            </provider>
            XML);

        $this->expectException(PaymentException::class);

        $this->reader()->readProvider($file);
    }

    public function testRejectsADeclarationWithoutTheRequiredElements(): void
    {
        $file = $this->writeXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <provider>
                <name>demo</name>
            </provider>
            XML);

        $this->expectException(PaymentException::class);

        $this->reader()->readProvider($file);
    }

    public function testRejectsAnUnreadableFile(): void
    {
        $this->expectException(PaymentException::class);

        $this->reader()->readProvider('/does/not/exist/provider.xml');
    }

    private function reader(): PaymentProviderSchemaReader
    {
        return new PaymentProviderSchemaReader();
    }

    private function channelOf(PaymentProviderDefinition $definition, string $technicalName): PaymentChannel
    {
        foreach ($definition->channels as $channel) {
            if ($channel->technicalName === $technicalName) {
                return $channel;
            }
        }

        static::fail(\sprintf('The provider does not declare the channel "%s".', $technicalName));
    }

    private function writeXml(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'payment-provider-');
        static::assertIsString($file);
        file_put_contents($file, $contents);

        $this->temporaryFiles[] = $file;

        return $file;
    }
}
