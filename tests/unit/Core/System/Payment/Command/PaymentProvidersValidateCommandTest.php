<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Command\PaymentProvidersValidateCommand;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Guards the CI entry point of the declaration mechanism.
 *
 * The command exists so that "this plugin's declaration is broken" fails a build instead of the first
 * payment of a shopper. It therefore has to fail on everything the registry refuses and on the two
 * things the registry cannot see: an interface without a channel and one without a credential field.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProvidersValidateCommand::class)]
class PaymentProvidersValidateCommandTest extends TestCase
{
    public function testPrintsEveryInterfaceWithItsCapabilitiesAndItsChain(): void
    {
        $tester = $this->tester(
            [$this->provider('alipay')],
            // the compiled container stores the chain outermost first, see PaymentProviderCompilerPass
            ['alipay' => ['MyAlipayDecorator', 'AlipayProvider']],
        );

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        static::assertStringContainsString('alipay', $display);
        static::assertStringContainsString('capture, refund', $display);
        static::assertStringContainsString('MyAlipayDecorator < AlipayProvider', $display);
        static::assertStringContainsString('1 interface(s), 1 channel(s).', $display);
        static::assertStringContainsString('Every declared payment interface is coherent.', $display);
    }

    /**
     * An interface nobody decorates has a chain of one entry, and printing that entry would read as if
     * the class were a decorator of itself.
     */
    public function testPrintsADashForAnInterfaceNobodyDecorates(): void
    {
        $tester = $this->tester(
            [$this->provider('alipay')],
            ['alipay' => ['AlipayProvider']],
        );

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        // the row ends with a single dash, the column of the chain stays empty of class names
        static::assertMatchesRegularExpression('/alipay\s+1\s+capture, refund\s+-\s/', $display);
        static::assertStringNotContainsString('AlipayProvider', $display);
    }

    /**
     * The registry builds every declaration when it is constructed, which is why the command resolves
     * it inside `execute()`: a broken declaration has to end up as an error message of this command and
     * not as a container failure while the command is being wired.
     */
    public function testReportsAProviderThatCannotBeBuiltInsteadOfLettingTheExceptionEscape(): void
    {
        // the declarations are read while the registry is constructed, exactly like a broken declaration
        // makes the real registry throw on the first `get()`
        $broken = (static function (): \Generator {
            throw PaymentException::invalidProviderDeclaration('The declaration "Broken" names itself "otherpay".');
            // @phpstan-ignore-next-line the generator never reaches this line
            yield;
        })();

        $tester = new CommandTester(new PaymentProvidersValidateCommand(
            new ServiceLocator([
                PaymentProviderRegistry::class => static fn (): PaymentProviderRegistry => new PaymentProviderRegistry($broken),
            ]),
        ));

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('[ERROR]', $tester->getDisplay());
        static::assertStringContainsString('names itself "otherpay"', $tester->getDisplay());
    }

    public function testSucceedsWithoutReportingAnythingWhenNoInterfaceIsDeclared(): void
    {
        $tester = $this->tester([], []);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertStringContainsString('declares no payment interface at all', $tester->getDisplay());
    }

    public function testFailsWhenAChannelNameIsClaimedTwice(): void
    {
        $tester = $this->tester([
            $this->provider('alipay', channel: 'shared_qr'),
            $this->provider('wechat', channel: 'shared_qr'),
        ], []);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('shared_qr', $tester->getDisplay());
    }

    public function testFailsWhenAnInterfaceOffersNoChannel(): void
    {
        $tester = $this->tester([$this->provider('alipay', channel: null)], []);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('declares no payment channel', $tester->getDisplay());
    }

    public function testFailsWhenAnInterfaceDeclaresNoCredential(): void
    {
        $tester = $this->tester([$this->provider('alipay', credentials: [])], []);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('declares no credential field', $tester->getDisplay());
    }

    /**
     * @param list<PaymentProvider> $providers
     * @param array<string, list<string>> $decorationChain
     */
    private function tester(array $providers, array $decorationChain): CommandTester
    {
        // a locator instead of the registry itself: the registry is constructed on the first `get()`,
        // which is what makes a broken declaration reportable by the command
        return new CommandTester(new PaymentProvidersValidateCommand(
            new ServiceLocator([
                PaymentProviderRegistry::class => static fn (): PaymentProviderRegistry => new PaymentProviderRegistry($providers),
            ]),
            $decorationChain,
        ));
    }

    /**
     * @param list<PaymentConfigField>|null $credentials
     */
    private function provider(
        string $technicalName,
        ?string $channel = 'test_channel',
        ?array $credentials = null,
    ): PaymentProvider {
        $channels = [];

        if ($channel !== null) {
            $channels[$channel] = new PaymentChannel(
                technicalName: $channel,
                providerTechnicalName: $technicalName,
                label: 'test.channel.label',
                flow: PaymentFlow::REDIRECT,
            );
        }

        return new PaymentProvider(
            technicalName: $technicalName,
            label: 'sw-settings-payment-provider.provider.' . $technicalName,
            credentials: $credentials ?? [new PaymentConfigField(name: 'api_key')],
            channels: $channels,
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: [PaymentCapability::CAPTURE, PaymentCapability::REFUND],
        );
    }
}
