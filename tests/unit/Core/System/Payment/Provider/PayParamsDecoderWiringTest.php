<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Guards the wiring of the shipped pay params decoders.
 *
 * `PaymentProvider::$payParamsDecoder` is optional so that the many places constructing a provider by
 * hand keep working. The price of that is silence: a provider added without a decoder would hand a
 * returning payer to "capture again" without anyone noticing, and a second capture may open a second
 * trade at an interface that is not idempotent on the out trade number. This test turns "someone
 * forgot the decoder" from a silent null into a red build: every provider registered in the system
 * payment configuration either carries a decoder or is named in the whitelist below.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProvider::class)]
class PayParamsDecoderWiringTest extends TestCase
{
    private const PROVIDER_SERVICE_PREFIX = 'shopwell.payment.provider.';

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
     * @var list<string>
     */
    private const WITHOUT_DECODER = ['paypal', 'stripe'];

    public function testEveryProviderWithABrowserFlowHasADecoder(): void
    {
        $providers = $this->registeredProviders();

        static::assertNotSame([], $providers, 'The payment configuration registers no provider at all.');

        $withDecoder = [];
        $withoutDecoder = [];

        foreach ($providers as $name => $definition) {
            $decoder = $definition->getArguments()[3] ?? null;

            if ($decoder instanceof Reference) {
                $id = (string) $decoder;

                static::assertTrue(
                    class_exists($id),
                    \sprintf('The provider "%s" references the decoder "%s", which is not a class.', $name, $id)
                );
                static::assertTrue(
                    is_subclass_of($id, PayParamsDecoderInterface::class),
                    \sprintf('The provider "%s" references "%s", which is not a pay params decoder.', $name, $id)
                );

                $withDecoder[] = $name;

                continue;
            }

            static::assertNull(
                $decoder,
                \sprintf('The provider "%s" passes something that is neither a decoder nor nothing.', $name)
            );

            $withoutDecoder[] = $name;
        }

        // the whitelist is a promise about these very providers: a renamed or removed provider must
        // not silently stay whitelisted, or the guard would protect nothing
        static::assertSame(
            [],
            array_values(array_diff($withoutDecoder, self::WITHOUT_DECODER)),
            'A provider without a pay params decoder must be listed in WITHOUT_DECODER with its reason.'
        );
        static::assertSame(
            [],
            array_values(array_diff(self::WITHOUT_DECODER, $withoutDecoder)),
            'The whitelist names a provider that either has a decoder or is not registered at all.'
        );

        static::assertSame(
            \count($providers),
            \count($withDecoder) + \count(self::WITHOUT_DECODER),
            \sprintf(
                'Expected every registered provider to carry a decoder except %s.',
                implode(', ', self::WITHOUT_DECODER)
            )
        );
    }

    /**
     * A provider that does have a decoder has to decode something: an implementation that always
     * returns null would satisfy the wiring above and reintroduce the silence.
     */
    public function testTheShippedDecodersDecodeTheAnswersTheirProviderRecords(): void
    {
        $decoded = [];

        foreach ($this->registeredProviders() as $name => $definition) {
            $decoder = $definition->getArguments()[3] ?? null;

            if (!$decoder instanceof Reference) {
                continue;
            }

            $class = (string) $decoder;
            /** @var PayParamsDecoderInterface $instance */
            $instance = new $class();

            $decodes = false;

            foreach ($this->sampleAnswers() as $answer) {
                if ($instance->payParamsFromResponse($answer) !== null) {
                    $decodes = true;

                    break;
                }
            }

            static::assertTrue(
                $decodes,
                \sprintf('The decoder of the provider "%s" decoded none of the sample answers.', $name)
            );

            $decoded[] = $name;
        }

        static::assertSame(
            ['alipay', 'wechat'],
            array_values(array_unique($decoded)),
            'The shipped decoders are expected to decode the redirect or qr answer of alipay and wechat.'
        );
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
     * The definitions of the system payment configuration, without compiling a container: the
     * definition of a provider is the one place that knows which decoder it was wired with.
     *
     * @return array<string, Definition>
     */
    private function registeredProviders(): array
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__));
        $loader->load(__DIR__ . '/../../../../../../src/Core/System/DependencyInjection/payment.php');

        $providers = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $id = (string) $id;

            if (!str_starts_with($id, self::PROVIDER_SERVICE_PREFIX)) {
                continue;
            }

            $providers[substr($id, \strlen(self::PROVIDER_SERVICE_PREFIX))] = $definition;
        }

        return $providers;
    }
}
