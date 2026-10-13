<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * The interface, the channel, the credentials and the parameters one payment works with.
 *
 * The credentials of the merchant account are resolved per payment - either the ones of the app or the
 * ones of the platform - which is exactly why the gateways are not configured statically and why every
 * action reaches for the credentials through this api object instead of through the container.
 *
 * The channel is part of it as well, because the channel is what tells the actions of an interface which
 * of its products the payment was opened with: an alipay api serves the mobile web payment and the qr
 * code payment alike, and only the capture of a payment cares which one it is.
 *
 * @internal
 */
#[Package('framework')]
interface PaymentApiInterface
{
    /**
     * The payment interface that is charged, e.g. `alipay`.
     */
    public function provider(): string;

    /**
     * The channel the payment was opened with, e.g. `alipay_h5`.
     */
    public function channel(): string;

    /**
     * Whether the payment is processed with the credentials of the merchant (`own`) or of the platform.
     */
    public function mode(): string;

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array;

    /**
     * @return array<string, mixed>
     */
    public function params(): array;

    /**
     * @return array<string, mixed>
     */
    public function runtimeParams(): array;

    /**
     * @return array<string, mixed>
     */
    public function settings(): array;

    public function credential(string $key, ?string $default = null): ?string;

    public function requireCredential(string $key): string;

    /**
     * A declared parameter of the channel, e.g. the appid of a wechat channel.
     */
    public function param(string $key, ?string $default = null): ?string;

    /**
     * A parameter the app handed in for this very payment, e.g. the openid of a wechat jsapi payment.
     */
    public function runtimeParam(string $key, ?string $default = null): ?string;

    public function setting(string $key, ?string $default = null): ?string;

    /**
     * Where the interface notifies the platform. It is built per payment and carries the payment number,
     * which is how an encrypted notification is matched to its app.
     */
    public function notifyUrl(): string;

    /**
     * Where the interface sends the payer back to after the payment.
     */
    public function returnUrl(): string;
}
