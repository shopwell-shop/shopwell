<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Stripe\StripeClient;

/**
 * The stripe api of one payment.
 *
 * Stripe takes the amount in the smallest unit of the currency, exactly like the platform stores it, so
 * no amount has to be converted for the api calls.
 *
 * The webhook is verified with the signing secret of the endpoint of the app, so every app verifies its
 * own notifications - an app may therefore run its own stripe account next to the one of the platform.
 *
 * @internal
 */
#[Package('framework')]
final class StripeApi extends AbstractPaymentApi
{
    final public const SECRET_KEY = 'secret_key';

    /**
     * The publishable key, only needed for the client side flow of the javascript sdk.
     */
    final public const PUBLISHABLE_KEY = 'publishable_key';

    /**
     * The signing secret of the webhook endpoint, `whsec_...`.
     */
    final public const WEBHOOK_SECRET = 'webhook_secret';

    final public const API_VERSION = 'api_version';

    private ?StripeClient $client = null;

    public function client(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        /** @var array<string, int|string|null> $config */
        $config = ['api_key' => $this->requireCredential(self::SECRET_KEY)];

        $version = $this->credential(self::API_VERSION);
        if ($version !== null) {
            // without a version stripe answers with the version the account is pinned to, which changes
            // over time - pinning it keeps the payloads the actions read stable
            $config['stripe_version'] = $version;
        }

        return $this->client = new StripeClient($config);
    }

    public function webhookSecret(): string
    {
        return $this->requireCredential(self::WEBHOOK_SECRET);
    }

    public function publishableKey(): string
    {
        return $this->credential(self::PUBLISHABLE_KEY) ?? '';
    }

    /**
     * Stripe wants the currency in lowercase, the platform stores it in uppercase.
     */
    public function currency(string $currency): string
    {
        return strtolower($currency);
    }
}
