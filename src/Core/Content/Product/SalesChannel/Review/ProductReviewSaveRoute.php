<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Review;

use Shopwell\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopwell\Core\Content\Product\Extension\ProductReviewSaveRouteExtension;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductReviewSaveRoute extends AbstractProductReviewSaveRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductReviewCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly DataValidator $validator,
        private readonly SystemConfigService $config,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductProvider $productProvider,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractProductReviewSaveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/review',
        name: 'store-api.product-review.save',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function save(string $productId, RequestDataBag $data, SalesChannelContext $context): NoContentResponse
    {
        return $this->extensions->publish(
            name: ProductReviewSaveRouteExtension::NAME,
            extension: new ProductReviewSaveRouteExtension($productId, $data, $context),
            function: $this->_save(...),
        );
    }

    private function _save(string $productId, RequestDataBag $data, SalesChannelContext $context): NoContentResponse
    {
        $salesChannelId = $context->getSalesChannelId();
        if (!$this->config->getBool('core.listing.showReview', $salesChannelId)) {
            throw ProductException::reviewNotActive();
        }

        $customer = $context->getCustomer();
        \assert($customer !== null);

        $customerId = $customer->getId();

        EmailIdnConverter::encodeDataBag($data);
        if (!$data->has('name')) {
            $data->set('name', $customer->getFirstName());
        }

        if (!$data->has('lastName')) {
            $data->set('lastName', $customer->getLastName());
        }

        if (!$data->has('email')) {
            $data->set('email', $customer->getEmail());
        }

        $data->set('customerId', $customerId);
        $data->set('productId', $productId);
        $this->validate($data, $context->getContext());

        $review = [
            'productId' => $productId,
            'customerId' => $customerId,
            'salesChannelId' => $salesChannelId,
            'languageId' => $context->getLanguageId(),
            'externalUser' => $data->get('name'),
            'externalEmail' => $data->get('email'),
            'title' => $data->get('title'),
            'content' => $data->get('content'),
            'points' => $data->get('points'),
            'status' => false,
        ];

        if ($data->get('id')) {
            $review['id'] = $data->get('id');
        }

        $product = $this->productProvider->getData($productId, $context->getContext());
        if ($product === null) {
            throw ProductException::productNotFound($productId);
        }

        $this->repository->upsert([$review], $context->getContext());

        $mail = $review['externalEmail'];
        $mail = \is_string($mail) ? $mail : '';
        $event = new ReviewFormEvent(
            $context->getContext(),
            $salesChannelId,
            new MailRecipientStruct([$mail => $review['externalUser'] . ' ' . $data->get('lastName')]),
            $data,
            $productId,
            $customerId,
            $product
        );

        $this->eventDispatcher->dispatch(
            $event,
            ReviewFormEvent::EVENT_NAME
        );

        return new NoContentResponse();
    }

    private function validate(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('product.create_rating');

        $definition->add('name', new NotBlank());
        $definition->add('title', new NotBlank(), new Length(min: 5));
        $definition->add('content', new NotBlank(), new Length(min: 40));

        $definition->add('points', new GreaterThanOrEqual(1), new LessThanOrEqual(5));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $data->get('customerId')));

        if ($data->get('id')) {
            $criteria->addFilter(new EqualsFilter('id', $data->get('id')));

            $definition->add('id', new EntityExists(
                entity: 'product_review',
                context: $context,
                criteria: $criteria,
            ));
        } else {
            $criteria->addFilter(new EqualsFilter('productId', $data->get('productId')));

            $definition->add('customerId', new EntityNotExists(
                entity: 'product_review',
                context: $context,
                primaryProperty: 'customerId',
                criteria: $criteria,
            ));
        }

        $this->validator->validate($data->all(), $definition);

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }
}
