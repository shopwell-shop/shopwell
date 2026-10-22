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
 * every channel of it, the parameters belong to the channel, and both follow the account source of the
 * app - its own account, the one of another app it inherits, or the one of the platform.
 *
 * Two different questions are answered by two different members, and neither of them is a synonym of
 * "is this app the platform":
 *
 *  - `$sourceAppId` answers *whose* account is charged. It names the app the account was walked
 *    to, so an app of a merchant that inherits from the platform tenant carries the id of that tenant,
 *    and only the tenant itself carries `null`.
 *  - `$usesPlatformAccount` answers *where the credentials came from*, namely the configuration of
 *    the platform level. That is true for the tenant and for every app that inherits from it at the
 *    same time, which is exactly why `$sourceAppId` alone cannot express it - and it is false for an
 *    app that inherits from another merchant app, even though that one is inherited as well.
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
     * @param string|null $sourceAppId the app the account was inherited from, `null` when the payment is
     *                                 collected on an account of the app itself. The tenant of the
     *                                 platform is an app like every other here: an app that collects on
     *                                 the platform account carries its id, and only the tenant itself
     *                                 carries `null` - its own credentials *are* the platform ones.
     * @param bool $usesPlatformAccount whether the credentials are the ones of the platform level
     *                                  configuration. It is the one thing `sourceAppId` cannot answer
     *                                  on its own, because both the tenant and every app that inherits
     *                                  from it are charged on that very account - a caller that has to
     *                                  tell a merchant account of the platform from one an app brought
     *                                  has to ask this, not `isInherited()`
     */
    public function __construct(
        public PaymentProvider $provider,
        public PaymentChannel $channel,
        public array $credentials,
        public array $params,
        public array $runtimeParams,
        public array $settings,
        public ?string $sourceAppId = null,
        public bool $usesPlatformAccount = false,
    ) {
    }

    /**
     * Whether the account of another app is charged, the one of the platform included.
     */
    public function isInherited(): bool
    {
        return $this->sourceAppId !== null;
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
