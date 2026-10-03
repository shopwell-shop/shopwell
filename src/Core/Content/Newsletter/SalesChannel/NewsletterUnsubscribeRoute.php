<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\SalesChannel;

use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopwell\Core\Content\Newsletter\Extension\NewsletterUnsubscribeRouteExtension;
use Shopwell\Core\Content\Newsletter\NewsletterException;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class NewsletterUnsubscribeRoute extends AbstractNewsletterUnsubscribeRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RateLimiter $rateLimiter,
        private readonly RequestStack $requestStack,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractNewsletterUnsubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0
     * Use unsubscribeWithResponse() instead.
     * Starting with v6.8.0, the API route response is changing.
     * This method will be removed.
     */
    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                self::class,
                __FUNCTION__,
                'v6.8.0.0',
                'unsubscribeWithResponse()'
            )
        );

        $this->unsubscribeWithResponse($dataBag, $context);

        return new NoContentResponse();
    }

    #[Route(path: '/store-api/newsletter/unsubscribe', name: 'store-api.newsletter.unsubscribe', methods: ['POST'])]
    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return $this->extensions->publish(
            name: NewsletterUnsubscribeRouteExtension::NAME,
            extension: new NewsletterUnsubscribeRouteExtension($dataBag, $context),
            function: $this->_unsubscribeWithResponse(...),
        );
    }

    private function _unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::NEWSLETTER_UNSUBSCRIBE_FORM, $request->getClientIp());
        }

        $data = $dataBag->only('email');

        if (!isset($data['email']) || !\is_string($data['email']) || $data['email'] === '') {
            throw NewsletterException::missingEmailParameter();
        }

        $recipient = $this->getNewsletterRecipient($data['email'], $context);

        $data['id'] = $recipient->getId();
        $data['status'] = NewsletterSubscribeRoute::STATUS_OPT_OUT;

        $validator = $this->getOptOutValidation();
        $this->validator->validate($data, $validator);

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        $event = new NewsletterUnsubscribeEvent($context->getContext(), $recipient, $context->getSalesChannelId());
        $this->eventDispatcher->dispatch($event);

        return new SuccessResponse();
    }

    private function getNewsletterRecipient(string $email, SalesChannelContext $context): NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('email', $email),
            new EqualsFilter('salesChannelId', $context->getSalesChannelId())
        );
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $newsletterRecipient = $this->newsletterRecipientRepository->search(
            $criteria,
            $context->getContext()
        )->getEntities()->first();

        if (!$newsletterRecipient) {
            throw NewsletterException::recipientNotFound('email', $email);
        }

        return $newsletterRecipient;
    }

    private function getOptOutValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_out');
        $definition->add('email', new NotBlank(), new Email());

        return $definition;
    }
}
