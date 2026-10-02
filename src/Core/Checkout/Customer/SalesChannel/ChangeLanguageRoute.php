<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\ChangeLanguageRouteExtension;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\BuildValidationEvent;
use Shopwell\Core\Framework\Validation\Constraint\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeLanguageRoute extends AbstractChangeLanguageRoute
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
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractChangeLanguageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/change-language',
        name: 'store-api.account.change-language',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        return $this->extensions->publish(
            name: ChangeLanguageRouteExtension::NAME,
            extension: new ChangeLanguageRouteExtension($requestDataBag, $context, $customer),
            function: $this->_change(...),
        );
    }

    private function _change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        $this->validateLanguageId($requestDataBag, $context);

        $customerData = [
            'id' => $customer->getId(),
            'languageId' => $requestDataBag->get('languageId'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());

        return new SuccessResponse();
    }

    private function validateLanguageId(DataBag $data, SalesChannelContext $context): void
    {
        $validation = new DataValidationDefinition('customer.language.update');

        $languageCriteria = new Criteria([$data->get('languageId')]);
        $languageCriteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $validation->add('languageId', new Uuid())
            ->add('languageId', new EntityExists(entity: 'language', context: $context->getContext(), criteria: $languageCriteria));

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }
}
