<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives the asynchronous notifications of the payment interfaces.
 *
 * This is the only endpoint of the gateway that is called by an interface and not by an app, so there
 * is no app credential to check: what the interface sends is verified by the interface itself, see
 * {@see PaymentNotificationProcessor}. Nothing is written before that verification succeeded.
 *
 * The url carries the payment interface, not the channel: an interface signs - or, in the case of
 * wechat, encrypts - its notification, so the interface has to be known before the body can be read at
 * all. The answer is what tells the interface whether it has to repeat the notification, and every
 * interface expects something else - hence {@see PaymentNotificationAck}. A notification that is
 * rejected for a reason that will not change (bad signature, unknown order, mismatching amount) is
 * still acknowledged: the interface would repeat it forever otherwise.
 *
 * The payment number is part of the url the interface was given, because some interfaces encrypt their
 * notification and give away nothing else to match it to a payment.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
class NotifyController
{
    public function __construct(
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentNotificationProcessor $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/payment-api/v1/notify/{provider}/{paymentNo}',
        name: 'payment-api.notify',
        methods: [Request::METHOD_POST],
        requirements: ['provider' => '[A-Za-z0-9_\-]+']
    )]
    public function notify(Request $request, string $provider, ?string $paymentNo = null): Response
    {
        if (!$this->providerRegistry->has($provider)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $ack = $this->providerRegistry->get($provider)->notificationExtractor->acknowledge();

        try {
            $this->notificationService->process(
                $provider,
                $request->getContent(),
                $this->headersOf($request),
                $request->getClientIp(),
                $paymentNo
            );
        } catch (PaymentException $exception) {
            // the notification cannot be applied and will not become applicable by repeating it
            $this->log($provider, $exception->getMessage(), $exception);

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
     * The headers the channel signed its notification with, as `name => values`.
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

    private function log(string $channel, string $message, \Throwable $exception): void
    {
        $this->logger->warning('A payment notification of "{channel}" could not be processed: {message}', [
            'channel' => $channel,
            'message' => $message,
            'exception' => $exception,
        ]);
    }
}
