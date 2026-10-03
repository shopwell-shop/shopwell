<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Extension\ConvertGuestRouteExtension;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\BuildValidationEvent;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ConvertGuestRoute extends AbstractConvertGuestRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $passwordValidationFactory,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractConvertGuestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/convert-guest',
        name: 'store-api.account.convert-guest',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function convertGuest(
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        CustomerEntity $customer,
        ?DataValidationDefinition $additionalValidationDefinitions = null
    ): SuccessResponse {
        return $this->extensions->publish(
            name: ConvertGuestRouteExtension::NAME,
            extension: new ConvertGuestRouteExtension($requestDataBag, $context, $customer, $additionalValidationDefinitions),
            function: $this->_convertGuest(...),
        );
    }

    private function _convertGuest(
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        CustomerEntity $customer,
        ?DataValidationDefinition $additionalValidationDefinitions
    ): SuccessResponse {
        if (!$customer->getGuest()) {
            throw CustomerException::registeredCustomerCannotBeConverted($customer->getId());
        }

        if ($this->requestStack->getMainRequest() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $customer->getId());
        }

        $requestDataBag->add([
            'email' => $customer->getEmail(),
        ]);
        $this->validate($requestDataBag, $context, $additionalValidationDefinitions);

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'guest' => false,
                'password' => $requestDataBag->get('password'),
            ],
        ], $context->getContext());

        return new SuccessResponse();
    }

    private function validate(RequestDataBag $data, SalesChannelContext $context, ?DataValidationDefinition $additionalValidationDefinitions = null): void
    {
        $definition = new DataValidationDefinition('customer.guest.convert');
        $definition->merge($this->passwordValidationFactory->create($context));

        if ($additionalValidationDefinitions) {
            $definition->merge($additionalValidationDefinitions);
        }

        $definition->add('email', new CustomerEmailUnique(salesChannelContext: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}
