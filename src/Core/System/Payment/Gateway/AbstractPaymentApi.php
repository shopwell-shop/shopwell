<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Base of the api objects of the payment interfaces: it holds the interface, the channel, the
 * credentials, the parameters and the urls the gateway was created with and hands them to the actions.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractPaymentApi implements PaymentApiInterface
{
    final public const CONFIG_PROVIDER = 'payment.provider';
    final public const CONFIG_CHANNEL = 'payment.channel';
    final public const CONFIG_MODE = 'payment.mode';
    final public const CONFIG_CREDENTIALS = 'payment.credentials';
    final public const CONFIG_PARAMS = 'payment.params';
    final public const CONFIG_RUNTIME_PARAMS = 'payment.runtime_params';
    final public const CONFIG_SETTINGS = 'payment.settings';
    final public const CONFIG_NOTIFY_URL = 'payment.notify_url';
    final public const CONFIG_RETURN_URL = 'payment.return_url';

    /**
     * @var array<string, mixed>
     */
    private array $credentials;

    /**
     * @var array<string, mixed>
     */
    private array $params;

    /**
     * @var array<string, mixed>
     */
    private array $runtimeParams;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    private string $provider;

    private string $channel;

    private string $mode;

    private string $notifyUrl;

    private string $returnUrl;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->provider = self::stringOf($config, self::CONFIG_PROVIDER);
        $this->channel = self::stringOf($config, self::CONFIG_CHANNEL);
        $this->mode = self::stringOf($config, self::CONFIG_MODE);
        $this->notifyUrl = self::stringOf($config, self::CONFIG_NOTIFY_URL);
        $this->returnUrl = self::stringOf($config, self::CONFIG_RETURN_URL);

        $this->credentials = self::arrayOf($config, self::CONFIG_CREDENTIALS);
        $this->params = self::arrayOf($config, self::CONFIG_PARAMS);
        $this->runtimeParams = self::arrayOf($config, self::CONFIG_RUNTIME_PARAMS);
        $this->settings = self::arrayOf($config, self::CONFIG_SETTINGS);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return $this->credentials;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @return array<string, mixed>
     */
    public function runtimeParams(): array
    {
        return $this->runtimeParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    public function credential(string $key, ?string $default = null): ?string
    {
        return self::scalarOf($this->credentials, $key, $default);
    }

    /**
     * @throws PaymentException
     */
    public function requireCredential(string $key): string
    {
        $value = $this->credential($key);

        if ($value === null) {
            throw PaymentException::missingCredentials($this->provider(), $key);
        }

        return $value;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return self::scalarOf($this->params, $key, $default);
    }

    /**
     * @throws PaymentException
     */
    public function requireParam(string $key): string
    {
        $value = $this->param($key);

        if ($value === null) {
            throw PaymentException::channelNotConfigured($this->channel(), $key);
        }

        return $value;
    }

    public function runtimeParam(string $key, ?string $default = null): ?string
    {
        return self::scalarOf($this->runtimeParams, $key, $default);
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        return self::scalarOf($this->settings, $key, $default);
    }

    public function notifyUrl(): string
    {
        return $this->notifyUrl;
    }

    public function returnUrl(): string
    {
        return $this->returnUrl;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function stringOf(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function arrayOf(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function scalarOf(array $values, string $key, ?string $default): ?string
    {
        $value = $values[$key] ?? null;

        return \is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }
}
