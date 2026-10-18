<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationProcessor;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives the asynchronous payout notifications of the payment interfaces.
 *
 * The url names the app and not the payout: stripe, paypal and wechat notify a merchant account, so a
 * single url serves every payout of that account, and the payout number is read from the signed body.
 * The body is never interpreted here - it only triggers a query of the interface, see
 * {@see PaymentTransferNotificationProcessor}.
 *
 * This is the second endpoint of the gateway that is called by an interface and not by an app, so it
 * is not behind the app authentication either: what the interface sends is verified by the interface
 * itself. An unknown interface or an unknown app is answered with a 404 and without detail, so the
 * endpoint does not tell an unauthenticated caller which merchant accounts exist.
 *
 * A notification that was rejected for a reason that will not change (bad signature, unknown payout)
 * is still acknowledged - the interface would repeat it forever otherwise. Only an error of this
 * installation answers 5xx, so that the interface sends the notification again.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
final class PaymentTransferNotifyController
{
    public function __construct(
        private readonly PaymentTransferNotificationVerifierRegistry $verifierRegistry,
        private readonly PaymentTransferNotificationProcessor $processor,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/payment-api/v1/transfer-notify/{provider}/{appId}',
        name: 'payment-api.transfer-notify',
        methods: [Request::METHOD_POST],
        requirements: ['provider' => '[A-Za-z0-9_\-]+']
    )]
    public function notify(Request $request, string $provider, string $appId): Response
    {
        if (!$this->verifierRegistry->has($provider)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $ack = $this->verifierRegistry->get($provider)->acknowledge();

        try {
            $this->processor->process(
                $provider,
                $appId,
                $request->getContent(),
                $this->headersOf($request),
                $request->getClientIp()
            );
        } catch (PaymentException $exception) {
            $this->log($provider, $exception->getMessage(), $exception);

            if ($exception->is(PaymentException::TRANSFER_NOTIFICATION_APP_UNKNOWN)) {
                return new Response('', Response::HTTP_NOT_FOUND);
            }

            // the notification cannot be applied and will not become applicable by repeating it
            return $this->acknowledge($ack);
        } catch (\Throwable $exception) {
            // something on our side is broken, so ask the interface to send it again
            $this->log($provider, $exception->getMessage(), $exception);

            return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->acknowledge($ack);
    }

    private function acknowledge(PaymentNotificationAck $ack): Response
    {
        return new Response($ack->body, Response::HTTP_OK, ['Content-Type' => $ack->contentType]);
    }

    /**
     * The headers the interface signed its notification with, as `name => values`.
     *
     * @return array<string, list<string>>
     */
    private function headersOf(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[(string) $name] = array_values(array_filter(
                $values,
                static fn (?string $value): bool => $value !== null
            ));
        }

        return $headers;
    }

    private function log(string $provider, string $message, \Throwable $exception): void
    {
        $this->logger->warning('A payout notification of "{provider}" could not be processed: {message}', [
            'provider' => $provider,
            'message' => $message,
            'exception' => $exception,
        ]);
    }
}
