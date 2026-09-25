<?php declare(strict_types=1);

namespace Shopwell\Core\Content\RevocationRequest\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopwell\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopwell\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class RevocationRequestRoute extends AbstractRevocationRequestRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidationFactoryInterface $revocationRequestFormValidationFactory,
        private readonly DataValidator $validator,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly CmsFormSlotConfigResolver $cmsFormSlotConfigResolver,
    ) {
    }

    public function getDecorated(): AbstractRevocationRequestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/revocation-request-form', name: 'store-api.revocation-request.form', methods: [Request::METHOD_POST])]
    public function request(RequestDataBag $dataBag, SalesChannelContext $context): RevocationRequestRouteResponse
    {
        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::REVOCATION_REQUEST_FORM, $request->getClientIp());
        }

        EmailIdnConverter::encodeDataBag($dataBag);
        $dataBag->set('submitTime', $this->clock->now());

        $this->validateRevocationRequestForm($dataBag, $context);

        $mailConfig = $this->cmsFormSlotConfigResolver->resolve($context, $dataBag->get('slotId'), $dataBag->get('navigationId'), $dataBag->get('entityName'));

        $merchantMailRecipientStruct = new MailRecipientStruct($mailConfig['receivers']);
        $merchantEvent = new RevocationRequestEvent($context->getContext(), $context->getSalesChannelId(), $merchantMailRecipientStruct, $dataBag);
        $this->eventDispatcher->dispatch($merchantEvent, RevocationRequestEvent::EVENT_NAME);

        return new RevocationRequestRouteResponse($mailConfig['message'] ?? '');
    }

    private function validateRevocationRequestForm(DataBag $dataBag, SalesChannelContext $context): void
    {
        $definition = $this->revocationRequestFormValidationFactory->create($context);
        $violations = $this->validator->getViolations($dataBag->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $dataBag->all());
        }
    }
}
