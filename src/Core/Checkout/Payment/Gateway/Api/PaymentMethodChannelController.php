<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway\Api;

use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelBinder;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Configures the logical payment method, app and routing policy used by checkout.
 * Selectable app labels and authorized methods share the checkout method's read permission.
 * Merchant credentials and account configuration are excluded from this response.
 *
 * @internal
 */
#[Package('checkout')]
class PaymentMethodChannelController extends AbstractController
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly PaymentMethodChannelBinder $binder,
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    #[Route(
        path: '/api/_action/payment/methods/{paymentMethodId}/channel',
        name: 'api.action.payment.method.channel',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_method:read'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function read(string $paymentMethodId, Context $context): JsonResponse
    {
        $paymentMethod = $this->paymentMethod($paymentMethodId, $context);
        $binding = $this->binder->binding($paymentMethodId, $context);

        return new JsonResponse($this->present(
            $binding,
            $paymentMethod,
            $this->binder->taken($paymentMethodId, $context),
            $this->binder->appOf($binding, $context),
            $this->binder->selectableApps($context),
        ));
    }

    /**
     * Saves the app, logical method and routing policy together with the gateway handler.
     */
    #[Route(
        path: '/api/_action/payment/methods/{paymentMethodId}/channel',
        name: 'api.action.payment.method.channel.save',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_method:update'],
        ],
        methods: [Request::METHOD_PUT]
    )]
    public function save(string $paymentMethodId, #[MapRequestPayload] PaymentMethodChannelSaveDto $dto, Context $context): JsonResponse
    {
        $this->binder->bind(
            $paymentMethodId,
            (string) $dto->methodTechnicalName,
            $dto->paymentAppId,
            $context,
            $dto->routingPolicy,
        );

        $paymentMethod = $this->paymentMethod($paymentMethodId, $context);
        $binding = $this->binder->binding($paymentMethodId, $context);

        return new JsonResponse($this->present(
            $binding,
            $paymentMethod,
            $this->binder->taken($paymentMethodId, $context),
            $this->binder->appOf($binding, $context),
            $this->binder->selectableApps($context),
        ));
    }

    /**
     * @param list<array{paymentAppId: string, paymentAppName: string, methodTechnicalName: string, paymentMethodId: string, paymentMethodName: string}> $taken
     * @param list<array<string, mixed>> $apps
     *
     * @return array<string, mixed>
     */
    private function present(
        ?PaymentMethodGatewayChannelEntity $binding,
        PaymentMethodEntity $paymentMethod,
        array $taken,
        PaymentAppEntity $app,
        array $apps,
    ): array {
        return [
            'binding' => $binding instanceof PaymentMethodGatewayChannelEntity ? [
                'paymentAppId' => $binding->paymentAppId,
                'methodTechnicalName' => $binding->methodTechnicalName,
                'routingPolicy' => $binding->routingPolicy,
            ] : null,
            'paymentApp' => [
                'id' => $app->id,
                'name' => isset($app->name) ? $app->name : '',
                'technicalName' => $app->technicalName,
                'type' => $app->type,
            ],
            'collectThroughGateway' => $paymentMethod->getHandlerIdentifier() === PaymentGatewayHandler::class,
            'taken' => $taken,
            'apps' => $apps,
        ];
    }

    /**
     * @throws CheckoutPaymentException
     */
    private function paymentMethod(string $paymentMethodId, Context $context): PaymentMethodEntity
    {
        $paymentMethod = $this->paymentMethodRepository
            ->search(new Criteria([$paymentMethodId]), $context)
            ->getEntities()
            ->first();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            throw CheckoutPaymentException::unknownPaymentMethodById($paymentMethodId);
        }

        return $paymentMethod;
    }
}
