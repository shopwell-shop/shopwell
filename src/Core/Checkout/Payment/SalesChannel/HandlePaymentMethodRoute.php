<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\SalesChannel;

use Shopwell\Core\Checkout\Payment\Extension\HandlePaymentMethodRouteExtension;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\PaymentProcessor;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class HandlePaymentMethodRoute extends AbstractHandlePaymentMethodRoute
{
    /**
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     *
     * @internal
     */
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly DataValidator $dataValidator,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly EntityRepository $currencyRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractHandlePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/handle-payment',
        name: 'store-api.payment.handle',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: ['GET', 'POST']
    )]
    public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse
    {
        return $this->extensions->publish(
            name: HandlePaymentMethodRouteExtension::NAME,
            extension: new HandlePaymentMethodRouteExtension($request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse
    {
        $data = [...$request->query->all(), ...$request->request->all()];
        $this->dataValidator->validate($data, $this->createDataValidation());
        /** @var array{orderId: string, finishUrl?: string, errorUrl?: string} $data */
        $orderCurrencyId = $this->getCurrencyFromOrder($data['orderId'], $context->getContext());

        if ($context->getCurrencyId() !== $orderCurrencyId) {
            $context = $this->contextService->get(
                new SalesChannelContextServiceParameters(
                    $context->getSalesChannelId(),
                    $context->getToken(),
                    $context->getLanguageId(),
                    $orderCurrencyId,
                )
            );
        }

        $response = $this->paymentProcessor->pay(
            $data['orderId'],
            $request,
            $context,
            $data['finishUrl'] ?? null,
            $data['errorUrl'] ?? null,
        );

        return new HandlePaymentMethodRouteResponse($response);
    }

    private function createDataValidation(): DataValidationDefinition
    {
        return (new DataValidationDefinition())
            ->add('orderId', new NotBlank(), new Type('string'))
            ->add('finishUrl', new Type('string'))
            ->add('errorUrl', new Type('string'));
    }

    private function getCurrencyFromOrder(string $orderId, Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orders.id', $orderId));

        $id = $this->currencyRepository->searchIds($criteria, $context)->firstId();
        if (!$id) {
            throw PaymentException::invalidOrder($orderId);
        }

        return $id;
    }
}
