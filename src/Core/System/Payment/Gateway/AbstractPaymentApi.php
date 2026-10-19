<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * Base of the api objects of the payment interfaces: it holds the resolved configuration of the very
 * payment - the interface, the channel, the credentials, the parameters and the urls - and hands it
 * to the actions.
 *
 * The credentials of the merchant account are resolved per payment - either the ones the app brings
 * itself or the ones of the app it inherits its account from, the platform tenant in particular -
 * which is exactly why the gateways are not configured statically and why
 * every action reaches for the credentials through this api object instead of through the container.
 * The channel is part of it as well, because the channel is what tells the actions of an interface
 * which of its products the payment was opened with: an alipay api serves the mobile web payment and
 * the qr code payment alike, and only the capture of a payment cares which one it is.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractPaymentApi
{
    public function __construct(
        protected readonly ResolvedPaymentConfig $config,
        protected readonly string $notifyUrl,
        protected readonly string $returnUrl,
    ) {
    }

    /**
     * The payment interface that is charged, e.g. `alipay`.
     */
    public function provider(): string
    {
        return $this->config->provider->technicalName;
    }

    /**
     * The channel the payment was opened with, e.g. `alipay_h5`.
     */
    public function channel(): string
    {
        return $this->config->channel->technicalName;
    }

    /**
     * The app whose merchant account is charged, `null` when the payment is collected on an account
     * the app brings itself.
     */
    public function sourceAppId(): ?string
    {
        return $this->config->sourceAppId;
    }

    /**
     * Whether the payment runs on the merchant account of another app - the tenant of the platform in
     * particular - instead of on an account of the app itself.
     */
    public function inherited(): bool
    {
        return $this->config->isInherited();
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return $this->config->credentials;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->config->params;
    }

    /**
     * @return array<string, mixed>
     */
    public function runtimeParams(): array
    {
        return $this->config->runtimeParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->config->settings;
    }

    public function credential(string $key, ?string $default = null): ?string
    {
        return $this->config->get($key, $default);
    }

    /**
     * @throws PaymentException when the credential of the merchant account is missing
     */
    public function requireCredential(string $key): string
    {
        return $this->config->require($key);
    }

    /**
     * A declared parameter of the channel, e.g. the appid of a wechat channel.
     */
    public function param(string $key, ?string $default = null): ?string
    {
        return $this->config->param($key, $default);
    }

    /**
     * @throws PaymentException when the channel parameter is missing
     */
    public function requireParam(string $key): string
    {
        return $this->config->requireParam($key);
    }

    /**
     * A parameter the app handed in for this very payment, e.g. the openid of a wechat jsapi payment.
     */
    public function runtimeParam(string $key, ?string $default = null): ?string
    {
        return $this->config->runtimeParam($key, $default);
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        return $this->config->setting($key, $default);
    }

    /**
     * Where the interface notifies the platform. It is built per payment and carries the payment number,
     * which is how an encrypted notification is matched to its app.
     */
    public function notifyUrl(): string
    {
        return $this->notifyUrl;
    }

    /**
     * Where the interface sends the payer back to after the payment.
     */
    public function returnUrl(): string
    {
        return $this->returnUrl;
    }
}
