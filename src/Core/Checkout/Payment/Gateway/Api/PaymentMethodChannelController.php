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
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reads and writes the gateway channel a checkout payment method collects through.
 *
 * The binding is a table of its own, and the administration deliberately does not write it through the
 * generic entity endpoints: binding a channel also has to put the gateway handler onto the payment
 * method, which no admin endpoint can do (the field is not `ApiAware`). Both writes are one decision,
 * so both live behind this one url - see {@see PaymentMethodChannelBinder}.
 *
 * `paymentApp` is answered next to the binding because it is the other half of the decision: the same
 * channel collects on a different merchant account depending on the app. A method that is not bound yet
 * answers with the tenant of the platform, i.e. the account the shop's own checkout would collect on -
 * that is what the operator is shown as the default, not an empty select.
 *
 * `collectThroughGateway` is reported next to the binding because the two can disagree for exactly one
 * moment: a method that was bound before this endpoint existed has the binding and not the handler. The
 * administration needs to tell the operator that saving the binding is what makes the method collect
 * through it, so the flag says what the checkout currently dispatches on, not what the binding says.
 *
 * `taken` carries the app/channel pairs other payment methods already collect through, so the
 * administration can grey them out instead of offering a choice that the binder refuses afterwards.
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
            $this->binder->appOf($binding, $context)
        ));
    }

    /**
     * Binds the payment method to the channel, or replaces the channel it collected through so far.
     *
     * There is no delete: taking a binding away would leave the method with the gateway handler and
     * without a channel, which is a state the checkout refuses on every selection. A method that should
     * stop collecting is deactivated, not unbound.
     *
     * @throws CheckoutPaymentException when the payment method does not exist
     * @throws PaymentException when the app, the interface or the channel is unknown, not configured or taken
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
        // the binder verifies that the method exists before it writes anything, so a typo in the url
        // never produces a binding row
        $this->binder->bind(
            $paymentMethodId,
            (string) $dto->channelTechnicalName,
            $dto->paymentAppId,
            $context
        );

        // read back after the write: the handler was changed as part of it, and the answer has to
        // describe the state the operator just created instead of the one the request assumed
        $paymentMethod = $this->paymentMethod($paymentMethodId, $context);
        $binding = $this->binder->binding($paymentMethodId, $context);

        return new JsonResponse($this->present(
            $binding,
            $paymentMethod,
            $this->binder->taken($paymentMethodId, $context),
            $this->binder->appOf($binding, $context)
        ));
    }

    /**
     * @param list<array{paymentAppId: string, paymentAppName: string, channelTechnicalName: string, paymentMethodId: string, paymentMethodName: string}> $taken
     *
     * @return array<string, mixed>
     */
    private function present(
        ?PaymentMethodGatewayChannelEntity $binding,
        PaymentMethodEntity $paymentMethod,
        array $taken,
        PaymentAppEntity $app
    ): array {
        return [
            'binding' => $binding instanceof PaymentMethodGatewayChannelEntity ? [
                'paymentAppId' => $binding->paymentAppId,
                'channelTechnicalName' => $binding->channelTechnicalName,
            ] : null,
            'paymentApp' => [
                'id' => $app->id,
                'name' => isset($app->name) ? $app->name : '',
                'technicalName' => $app->technicalName,
                'type' => $app->type,
            ],
            'collectThroughGateway' => $paymentMethod->getHandlerIdentifier() === PaymentGatewayHandler::class,
            'taken' => $taken,
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
