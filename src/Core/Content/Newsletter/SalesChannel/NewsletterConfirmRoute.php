<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopwell\Core\Content\Newsletter\NewsletterException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class NewsletterConfirmRoute extends AbstractNewsletterConfirmRoute
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
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0
     * Use confirmWithResponse() instead.
     * Starting with v6.8.0, the API route response is changing.
     * This method will be removed.
     */
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                self::class,
                __FUNCTION__,
                'v6.8.0.0',
                'confirmWithResponse()'
            )
        );

        $this->confirmWithResponse($dataBag, $context);

        return new NoContentResponse();
    }

    #[Route(path: '/store-api/newsletter/confirm', name: 'store-api.newsletter.confirm', methods: ['POST'])]
    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        $recipient = $this->getNewsletterRecipient('hash', $dataBag->get('hash', ''), $context->getContext());

        $data = [
            'id' => $recipient->getId(),
            'status' => $recipient->getStatus(),
            'confirmedAt' => $recipient->getConfirmedAt(),
            'em' => $dataBag->get('em'),
        ];

        $this->validator->validate($data, $this->getBeforeConfirmSubscribeValidation(Hasher::hash($recipient->getEmail(), 'sha1')));

        $data['status'] = NewsletterSubscribeRoute::STATUS_OPT_IN;
        $data['confirmedAt'] = $this->clock->now();

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannelId());
        $this->eventDispatcher->dispatch($event);

        return new SuccessResponse();
    }

    private function getNewsletterRecipient(string $identifier, string $value, Context $context): NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($identifier, $value));
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $context)->getEntities()->first();

        if (!$newsletterRecipient) {
            throw NewsletterException::recipientNotFound($identifier, $value);
        }

        return $newsletterRecipient;
    }

    private function getBeforeConfirmSubscribeValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_in_before');
        $definition->add('id', new NotBlank())
            ->add('status', new EqualTo(value: NewsletterSubscribeRoute::STATUS_NOT_SET))
            ->add('em', new EqualTo(value: $emHash));

        return $definition;
    }
}
