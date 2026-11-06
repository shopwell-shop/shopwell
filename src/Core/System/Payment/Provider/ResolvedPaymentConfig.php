<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentProduct;

/**
 * Execution configuration for one explicit merchant account and payment product.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedPaymentConfig
{
    /**
     * @param array<string, mixed> $credentials the account merchant credentials, decrypted
     * @param array<string, mixed> $params the account product parameters, decrypted
     * @param array<string, mixed> $runtimeParams the parameters of this very payment, handed in by the app
     * @param array<string, mixed> $settings the account settings merged with application grant and product settings
     */
    public function __construct(
        public PaymentProvider $provider,
        public PaymentProduct $product,
        public array $credentials,
        public array $params,
        public array $runtimeParams,
        public array $settings,
        public string $accountId,
        public int $priority = 0,
        public string $accountFingerprint = '',
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
            throw PaymentGatewayException::missingCredentials($this->provider->technicalName, $key);
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
            throw PaymentProviderException::channelNotConfigured($this->product->technicalName, $key);
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
