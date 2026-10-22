<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalProvider;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeProvider;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Guards the wiring of the shipped pay params decoders.
 *
 * `AbstractPaymentProvider::getPayParamsDecoder()` is optional so that a plugin does not have to
 * implement it. The price of that is silence: an interface added without a decoder hands a returning
 * payer to "capture again" without anyone noticing, and a second capture may open a second trade at an
 * interface that is not idempotent on the out trade number. This test turns "someone forgot the
 * decoder" from a silent null into a red build: every interface the platform declares either carries a
 * decoder or is named in the whitelist below.
 *
 * It reads the declarations from the service configuration instead of instantiating the declaration
 * classes, because a declaration takes its actions as constructor arguments - instantiating it here
 * would mean reproducing the whole gateway assembly.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractPaymentProvider::class)]
class PayParamsDecoderWiringTest extends TestCase
{
    /**
     * Interfaces that deliberately ship without a decoder, with the reason a returning payer is
     * captured again instead of being handed to the recorded answer.
     *
     * - paypal: the capture records the raw body of the create order call, and the approval url is
     *   only one of its links. Reading it here would be a second parser of paypal's response next to
     *   the capture action - a second capture is the acceptable price until that action records the
     *   approval url itself.
     * - stripe: the checkout opens a payment intent whose client secret is signed per call for the
     *   page that consumes it. There is no browser parameter that survives a reopen, so there is
     *   nothing to hand the payer to.
     *
     * @var array<string, class-string<AbstractPaymentProvider>>
     */
    private const WITHOUT_DECODER = [
        PaypalProvider::class => PaypalProvider::class,
        StripeProvider::class => StripeProvider::class,
    ];

    public function testEveryDeclarationOfThePlatformIsWiredWithItsDecoder(): void
    {
        $declarations = $this->declarationDefinitions();

        static::assertNotSame([], $declarations, 'The payment configuration declares no interface at all.');

        $withDecoder = [];
        $withoutDecoder = [];

        foreach ($declarations as $class => $definition) {
            $decoders = $this->decoderReferencesOf($definition);

            if ($decoders === []) {
                $withoutDecoder[$class] = $class;

                continue;
            }

            static::assertCount(
                1,
                $decoders,
                \sprintf('The declaration "%s" is wired with more than one pay params decoder.', $class)
            );

            $withDecoder[$class] = $decoders[0];
        }

        static::assertSame(
            [AlipayProvider::class => AlipayPayParamsDecoder::class, WechatProvider::class => WechatPayParamsDecoder::class],
            $withDecoder,
            'The interfaces with a browser flow are expected to hand the returning payer to their own decoder.'
        );
        static::assertSame(
            self::WITHOUT_DECODER,
            $withoutDecoder,
            'An interface without a pay params decoder must be listed in WITHOUT_DECODER with its reason.'
        );
    }

    /**
     * A decoder that always returns null would satisfy the wiring above and reintroduce the silence,
     * so the shipped ones have to prove that they decode the answers their capture action records.
     */
    public function testTheShippedDecodersDecodeTheAnswersTheirProviderRecords(): void
    {
        $decoders = [
            AlipayPayParamsDecoder::class,
            WechatPayParamsDecoder::class,
        ];

        foreach ($decoders as $class) {
            $decoder = new $class();

            $decodes = false;

            foreach ($this->sampleAnswers() as $answer) {
                if ($decoder->payParamsFromResponse($answer) !== null) {
                    $decodes = true;

                    break;
                }
            }

            static::assertTrue(
                $decodes,
                \sprintf('The decoder "%s" decoded none of the sample answers.', $class)
            );
        }
    }

    /**
     * One answer per browser flow the shipped interfaces record, in the shape their capture action
     * records it. Kept beside the wiring on purpose: it only has to be good enough to prove that a
     * decoder is not a no-op.
     *
     * @return list<array<string, mixed>>
     */
    private function sampleAnswers(): array
    {
        return [
            ['answer' => 'https://pay.example.test', 'http_method' => 'GET'],
            ['answer' => '<form></form>', 'http_method' => 'POST'],
            ['h5_url' => 'https://wx.tenpay.com/pay'],
            ['qr_code' => 'https://qr.alipay.com/abc'],
            ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'],
        ];
    }

    /**
     * The declaration services of the system payment configuration, without compiling a container: the
     * definition of a declaration is the one place that knows which decoder it was wired with.
     *
     * The PHP DSL of Symfony 7 registers a service by id and fills the class afterwards, so a loaded
     * definition may carry no class at all - the id is the class name then. Both are resolved through
     * the same method, so the key of the returned array is always the class and never the bare id.
     *
     * @return array<class-string, Definition>
     */
    private function declarationDefinitions(): array
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__));
        $loader->load(__DIR__ . '/../../../../../../src/Core/System/DependencyInjection/payment.php');

        $declarations = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $this->classOf((string) $id, $definition);

            if ($class === null || $class === AbstractPaymentProvider::class) {
                continue;
            }

            $declarations[$class] = $definition;
        }

        ksort($declarations);

        return $declarations;
    }

    /**
     * @return class-string|null
     */
    private function classOf(string $id, Definition $definition): ?string
    {
        $class = $definition->getClass() ?? $id;

        if (!class_exists($class) || !is_a($class, AbstractPaymentProvider::class, true)) {
            return null;
        }

        /** @var class-string<AbstractPaymentProvider> $class */
        return $class;
    }

    /**
     * @return list<class-string<PayParamsDecoderInterface>>
     */
    private function decoderReferencesOf(Definition $definition): array
    {
        $decoders = [];

        foreach ($definition->getArguments() as $argument) {
            if (!$argument instanceof Reference) {
                continue;
            }

            $id = (string) $argument;

            if (!is_a($id, PayParamsDecoderInterface::class, true)) {
                continue;
            }

            /** @var class-string<PayParamsDecoderInterface> $id */
            $decoders[] = $id;
        }

        return $decoders;
    }
}
