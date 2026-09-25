<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Controller;

use Shopwell\Core\Checkout\Cart\ApiOrderCartService;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Processor;
use Shopwell\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopwell\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\Framework\Validation\BuildValidationEvent;
use Shopwell\Core\Framework\Validation\Constraint\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopwell\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SalesChannelProxyController extends AbstractController
{
    private const CUSTOMER_ID = SalesChannelContextService::CUSTOMER_ID;

    private const SALES_CHANNEL_ID = 'salesChannelId';

    private const SEARCH_ROUTE = 'search';

    private const ADMIN_ORDER_PERMISSIONS = [
        CheckoutPermissions::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
    ];

    protected Processor $processor;

    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityRepository $salesChannelRepository,
        protected DataValidator $validator,
        protected SalesChannelContextPersister $contextPersister,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ApiOrderCartService $adminOrderCartService,
        private readonly AbstractCartOrderRoute $orderRoute,
        private readonly CartService $cartService,
        private readonly RequestStack $requestStack,
        private readonly ImitateCustomerTokenGenerator $imitateCustomerTokenGenerator
    ) {
    }

    #[Route(
        path: '/api/_proxy/store-api/{salesChannelId}/{_path}',
        name: 'api.proxy.store-api',
        requirements: ['_path' => '.*']
    )]
    public function proxy(string $_path, string $salesChannelId, Request $request, Context $context): Response
    {
        $salesChannel = $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelApiRequest = $this->setUpSalesChannelApiRequest($_path, $salesChannelId, $request, $salesChannel, $context);

        return $this->wrapInSalesChannelApiRoute($salesChannelApiRequest, fn (): Response => $this->kernel->handle($salesChannelApiRequest, HttpKernelInterface::SUB_REQUEST));
    }

    #[Route(
        path: '/api/_proxy-order/{salesChannelId}',
        name: 'api.proxy-order.create'
    )]
    public function proxyCreateOrder(string $salesChannelId, Request $request, Context $context, RequestDataBag $data): Response
    {
        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        if ($request->request->get('sendOrderConfirmationMail', true) === false) {
            $salesChannelContext->getContext()->addExtension(
                SendMailAction::MAIL_CONFIG_EXTENSION,
                new MailSendSubscriberConfig(true)
            );
        }

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $order = $this->orderRoute->order($cart, $salesChannelContext, $data)->getOrder();

        return new JsonResponse($order);
    }

    #[Route(
        path: '/api/_proxy/switch-customer',
        name: 'api.proxy.switch-customer',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_proxy_switch-customer']],
        methods: [Request::METHOD_PATCH]
    )]
    public function assignCustomer(Request $request, Context $context): Response
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw ApiException::salesChannelIdParameterIsMissing();
        }

        $salesChannelId = (string) $request->request->get('salesChannelId');

        if (!$request->request->has(self::CUSTOMER_ID)) {
            throw ApiException::salesChannelIdParameterIsMissing();
        }

        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $this->persistPermissions($request, $salesChannelContext);

        $this->updateCustomerToContext(RequestParamHelper::get($request, self::CUSTOMER_ID), $salesChannelContext);

        $content = json_encode([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $salesChannelContext->getToken(),
        ], \JSON_THROW_ON_ERROR);
        $response = new Response();
        $response->headers->set('content-type', 'application/json');
        $response->setContent($content ?: null);

        return $response;
    }

    #[Route(
        path: '/api/_proxy/generate-imitate-customer-token',
        name: 'api.proxy.generate-imitate-customer-token',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_proxy_imitate-customer']],
        methods: [Request::METHOD_POST]
    )]
    public function generateImitateCustomerToken(RequestDataBag $data, Context $context): JsonResponse
    {
        $this->validateImitateCustomerDataFields($data, $context);

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class);
        }

        $userId = $source->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn();
        }

        $salesChannelId = $data->getString(self::SALES_CHANNEL_ID);
        $customerId = $data->getString(self::CUSTOMER_ID);

        if (Feature::isActive('v6.8.0.0')) {
            $token = new ImitateCustomerToken();
            $token->salesChannelId = $salesChannelId;
            $token->customerId = $customerId;
            $token->iss = $userId;

            $token = $this->imitateCustomerTokenGenerator->encode($token);
        } else {
            $token = Feature::silent('v6.8.0.0', fn () => $this->imitateCustomerTokenGenerator->generate($salesChannelId, $customerId, $userId));
        }

        return new JsonResponse([
            'token' => $token,
        ]);
    }

    #[Route(
        path: '/api/_proxy/modify-shipping-costs',
        name: 'api.proxy.modify-shipping-costs',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function modifyShippingCosts(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw ApiException::salesChannelIdParameterIsMissing();
        }

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $calculatedPrice = $this->parseCalculatedPriceByRequest($request);

        $cart = $this->adminOrderCartService->updateShippingCosts($calculatedPrice, $salesChannelContext);

        return new JsonResponse(['data' => $cart]);
    }

    #[Route(
        path: '/api/_proxy/disable-automatic-promotions',
        name: 'api.proxy.disable-automatic-promotions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function disableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw ApiException::salesChannelIdParameterIsMissing();
        }

        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->adminOrderCartService->addPermission($contextToken, CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $salesChannelId);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/_proxy/enable-automatic-promotions',
        name: 'api.proxy.enable-automatic-promotions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function enableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw ApiException::salesChannelIdParameterIsMissing();
        }

        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->adminOrderCartService->deletePermission($contextToken, CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $salesChannelId);

        return new JsonResponse();
    }

    /**
     * @param callable(): Response $call
     */
    private function wrapInSalesChannelApiRoute(Request $request, callable $call): Response
    {
        $requestStackBackup = $this->clearRequestStackWithBackup($this->requestStack);
        $this->requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($this->requestStack, $requestStackBackup);
        }
    }

    private function setUpSalesChannelApiRequest(
        string $path,
        string $salesChannelId,
        Request $request,
        SalesChannelEntity $salesChannel,
        Context $context
    ): Request {
        $contextToken = $this->getContextToken($request);

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/store-api/' . $path]);
        $subrequest = $request->duplicate(null, null, [], null, null, $server);

        $subrequest->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $salesChannel->getAccessKey());
        $subrequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $salesChannel->getAccessKey());

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $subrequest, $context);

        if ($path === self::SEARCH_ROUTE) {
            $salesChannelContext->getContext()->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        }

        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $salesChannelContext->getContext());

        return $subrequest;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidSalesChannelIdException
     */
    private function fetchSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->getEntities()->get($salesChannelId);

        if ($salesChannel === null) {
            throw ApiException::invalidSalesChannelId($salesChannelId);
        }

        return $salesChannel;
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateImitateCustomerDataFields(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('impersonation.generate-token');

        $definition
            ->add(self::SALES_CHANNEL_ID, new Uuid(), new EntityExists(entity: 'sales_channel', context: $context))
            ->add(self::CUSTOMER_ID, new Uuid(), new EntityExists(entity: 'customer', context: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }

    private function getContextToken(Request $request): string
    {
        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if ($contextToken === null) {
            $contextToken = Random::getAlphanumericString(32);
        }

        return $contextToken;
    }

    /**
     * @return array<Request>
     */
    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMainRequest()) {
            $request = $requestStack->pop();

            if ($request === null) {
                continue;
            }

            $requestStackBackup[] = $request;
        }

        return $requestStackBackup;
    }

    /**
     * @param array<Request> $requestStackBackup
     */
    private function restoreRequestStack(RequestStack $requestStack, array $requestStackBackup): void
    {
        $this->clearRequestStackWithBackup($requestStack);

        foreach ($requestStackBackup as $backedUpRequest) {
            $requestStack->push($backedUpRequest);
        }
    }

    private function fetchSalesChannelContext(
        string $salesChannelId,
        Request $request,
        Context $originalContext
    ): SalesChannelContext {
        $contextToken = $this->getContextToken($request);

        return $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $salesChannelId,
                $contextToken,
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
                null,
                $originalContext
            )
        );
    }

    private function updateCustomerToContext(string $customerId, SalesChannelContext $context): void
    {
        $data = new DataBag();
        $data->set(self::CUSTOMER_ID, $customerId);

        $definition = new DataValidationDefinition('context_switch');
        $parameters = $data->only(
            self::CUSTOMER_ID
        );

        $customerCriteria = new Criteria();
        $customerCriteria->addFilter(new EqualsFilter('customer.id', $parameters[self::CUSTOMER_ID]));

        $definition
            ->add(self::CUSTOMER_ID, new EntityExists(entity: 'customer', context: $context->getContext(), criteria: $customerCriteria))
        ;

        $this->validator->validate($parameters, $definition);

        $isSwitchNewCustomer = true;
        if ($context->getCustomer()) {
            // Check if customer switch to another customer or not
            $isSwitchNewCustomer = $context->getCustomerId() !== $parameters[self::CUSTOMER_ID];
        }

        if (!$isSwitchNewCustomer) {
            return;
        }

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $parameters[self::CUSTOMER_ID],
                'billingAddressId' => null,
                'shippingAddressId' => null,
                'shippingMethodId' => null,
                'paymentMethodId' => null,
                'languageId' => null,
                'currencyId' => null,
            ],
            $context->getSalesChannelId()
        );
        $event = new SalesChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);
    }

    private function persistPermissions(Request $request, SalesChannelContext $salesChannelContext): void
    {
        $contextToken = $salesChannelContext->getToken();

        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $payload = $this->contextPersister->load($contextToken, $salesChannelId);
        /** @var array<mixed>|null $requestPermissions */
        $requestPermissions = $request->request->all()[SalesChannelContextService::PERMISSIONS] ?? null;

        if (\in_array(SalesChannelContextService::PERMISSIONS, $payload, true) && !$requestPermissions) {
            return;
        }

        $payload[SalesChannelContextService::PERMISSIONS] = $requestPermissions
            ? \array_fill_keys($requestPermissions, true)
            : self::ADMIN_ORDER_PERMISSIONS;

        $this->contextPersister->save($contextToken, $payload, $salesChannelId);
    }

    private function parseCalculatedPriceByRequest(Request $request): CalculatedPrice
    {
        $shippingCosts = $this->validateShippingCostsParameters($request);

        return new CalculatedPrice($shippingCosts['unitPrice'], $shippingCosts['totalPrice'], new CalculatedTaxCollection(), new TaxRuleCollection());
    }

    /**
     * @return array{unitPrice: float, totalPrice: float}
     */
    private function validateShippingCostsParameters(Request $request): array
    {
        if (!$request->request->has('shippingCosts')) {
            throw ApiException::shippingCostsParameterIsMissing();
        }

        $validation = new DataValidationDefinition('shipping-cost');
        $validation->add('unitPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(value: 0));
        $validation->add('totalPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(value: 0));
        $this->validator->validate($request->request->all('shippingCosts'), $validation);

        /** @var array{unitPrice: float, totalPrice: float} $shippingCosts otherwise validator would have thrown */
        $shippingCosts = $request->request->all('shippingCosts');

        return $shippingCosts;
    }
}
