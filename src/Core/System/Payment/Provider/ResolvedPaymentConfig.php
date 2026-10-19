<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;

/**
 * Everything one payment needs: the channel the app asked for, the payment interface behind it, and the
 * credentials and parameters that were resolved for the app.
 *
 * The split is the whole point of the model: the credentials belong to the interface and are shared by
 * every channel of it, the parameters belong to the channel, and both fall back from the app to the
 * platform.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedPaymentConfig
{
    /**
     * @param array<string, mixed> $credentials the merchant credentials of the interface, decrypted
     * @param array<string, mixed> $params the channel parameters, app overrides applied
     * @param array<string, mixed> $runtimeParams the parameters of this very payment, handed in by the app
     * @param array<string, mixed> $settings the free settings of the interface and the channel, app overrides applied
     */
    public function __construct(
        public PaymentProvider $provider,
        public PaymentChannel $channel,
        public array $credentials,
        public array $params,
        public array $runtimeParams,
        public array $settings,
        public string $mode,
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return self::scalar($this->credentials[$key] ?? null, $default);
    }

    /**
     * @throws PaymentException
     */
    public function require(string $key): string
    {
        $value = $this->get($key);

        if ($value === null) {
            throw PaymentException::missingCredentials($this->provider->technicalName, $key);
        }

        return $value;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return self::scalar($this->params[$key] ?? null, $default);
    }

    /**
     * @throws PaymentException
     */
    public function requireParam(string $key): string
    {
        $value = $this->param($key);

        if ($value === null) {
            throw PaymentException::channelNotConfigured($this->channel->technicalName, $key);
        }

        return $value;
    }

    public function runtimeParam(string $key, ?string $default = null): ?string
    {
        return self::scalar($this->runtimeParams[$key] ?? null, $default);
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        return self::scalar($this->settings[$key] ?? null, $default);
    }

    private static function scalar(mixed $value, ?string $default): ?string
    {
        return \is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }
}
