<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ContactForm\SalesChannel;

use Shopwell\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopwell\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopwell\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContactFormRoute extends AbstractContactFormRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly DataValidationFactoryInterface $contactFormValidationFactory,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $salutationRepository,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly CmsFormSlotConfigResolver $cmsFormSlotConfigResolver,
    ) {
    }

    public function getDecorated(): AbstractContactFormRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/contact-form', name: 'store-api.contact.form', methods: ['POST'])]
    public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse
    {
        EmailIdnConverter::encodeDataBag($data);

        $this->validateContactForm($data, $context);

        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::CONTACT_FORM, $request->getClientIp());
        }

        $mailConfigs = $this->cmsFormSlotConfigResolver->resolve($context, $data->get('slotId'), $data->get('navigationId'), $data->get('entityName'));

        $salutationCriteria = new Criteria([$data->get('salutationId')]);
        $salutationSearchResult = $this->salutationRepository->search($salutationCriteria, $context->getContext());

        if ($salutationSearchResult->getEntities()->count() !== 0) {
            $data->set('salutation', $salutationSearchResult->getEntities()->first());
        }

        $event = new ContactFormEvent(
            $context->getContext(),
            $context->getSalesChannelId(),
            new MailRecipientStruct($mailConfigs['receivers']),
            $data
        );

        $this->eventDispatcher->dispatch(
            $event,
            ContactFormEvent::EVENT_NAME
        );

        $result = new ContactFormRouteResponseStruct();
        $result->assign([
            'individualSuccessMessage' => $mailConfigs['message'] ?? '',
        ]);

        return new ContactFormRouteResponse($result);
    }

    private function validateContactForm(DataBag $data, SalesChannelContext $context): void
    {
        $definition = $this->contactFormValidationFactory->create($context);
        $violations = $this->validator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }
}
