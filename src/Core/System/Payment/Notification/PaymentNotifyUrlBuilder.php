<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the url the payment interface notifies the platform at.
 *
 * It is built per payment order instead of being one global url, because it carries the payment number.
 * Some interfaces (wechat) encrypt their notification, so there is no way to tell which app a
 * notification belongs to - and therefore which credentials decrypt it - other than by the url it was
 * sent to.
 *
 * The url carries the payment interface and not the channel: it is the interface that signs (or
 * encrypts) a notification and therefore the interface whose credentials are needed to read it. The
 * channel of the payment is looked up from the payment order afterwards.
 *
 * The base url is taken from the `notify_base_url` setting of the app, then from the configured base
 * url and finally from the current request.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentNotifyUrlBuilder
{
    final public const PATH_PREFIX = '/payment-api/v1/notify';

    /**
     * The payout notifications are addressed per app and not per payout: stripe, paypal and wechat
     * notify an account, not a single transfer, so the url can only name whose account it is. Which
     * payout the notification is about is read from the signed body afterwards.
     */
    final public const PATH_PREFIX_TRANSFER = '/payment-api/v1/transfer-notify';

    final public const SETTING_BASE_URL = 'notify_base_url';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $baseUrl = '',
    ) {
    }

    public function build(PaymentAppEntity $app, string $providerTechnicalName, string $paymentNo): string
    {
        return $this->buildWithPrefix($app, self::PATH_PREFIX, $providerTechnicalName, $paymentNo);
    }

    /**
     * The url a payout notification is sent to.
     *
     * It carries the app and not the payout: the payout number is read from the signed body, because
     * the channels notify the merchant account and not the single payout. The base url is the very
     * same one the payment notifications use - an installation behind a proxy pins it once.
     */
    public function buildTransfer(PaymentAppEntity $app, string $providerTechnicalName): string
    {
        return rtrim($this->baseUrlOf($app), '/')
            . self::PATH_PREFIX_TRANSFER
            . '/'
            . rawurlencode($providerTechnicalName)
            . '/'
            . rawurlencode($app->id);
    }

    private function buildWithPrefix(PaymentAppEntity $app, string $prefix, string $providerTechnicalName, string $reference): string
    {
        return rtrim($this->baseUrlOf($app), '/')
            . $prefix
            . '/'
            . rawurlencode($providerTechnicalName)
            . '/'
            . rawurlencode($reference);
    }

    private function baseUrlOf(PaymentAppEntity $app): string
    {
        $setting = $app->settings[self::SETTING_BASE_URL] ?? null;

        if (\is_scalar($setting) && (string) $setting !== '') {
            return (string) $setting;
        }

        if ($this->baseUrl !== '') {
            return $this->baseUrl;
        }

        return $this->requestStack->getMainRequest()?->getSchemeAndHttpHost() ?? '';
    }
}
