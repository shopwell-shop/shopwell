<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\SalesChannel\Review;

use Shopwell\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Event\BlogCommentWriteEvent;
use Shopwell\Core\Content\Blog\Extension\BlogCommentSaveRouteExtension;
use Shopwell\Core\Content\Blog\Service\BlogCommentDraft;
use Shopwell\Core\Content\Blog\Service\BlogCommentService;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Validates a submitted comment, announces it and hands it to {@see BlogCommentService}.
 *
 * The route does three things and nothing else: it refuses when the shop switched comments off, it
 * validates the shape of the payload, and it refuses to write below an entry a visitor may not read.
 * Everything about the parent - same entry, approved, depth - belongs to the service, because the same
 * rules have to hold for the storefront form, an import and a plugin.
 *
 * The status is not part of the payload. It is resolved from the sales channel configuration inside the
 * service, so a crafted request cannot publish itself.
 *
 * The route is final and carries no abstract base: extensions hang themselves on
 * {@see BlogCommentWriteEvent}, which is dispatched before the write and can refuse it. A decorator
 * would force every shop to recompile the container and to re-implement the half of the method it
 * wanted to extend.
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
final class BlogCommentRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $blogRepository,
        private readonly DataValidator $validator,
        private readonly BlogCommentService $commentService,
        private readonly BlogSettings $settings,
        private readonly BlogVisibilityGuard $visibilityGuard,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @throws BlogException when the shop switched comments off or the entry is not readable
     * @throws ConstraintViolationException when the payload does not have the shape a comment needs
     */
    #[Route(
        path: '/store-api/blog/comment',
        name: 'store-api.blog-comment.save',
        methods: [Request::METHOD_POST],
    )]
    public function save(RequestDataBag $data, SalesChannelContext $context): NoContentResponse
    {
        return $this->extensions->publish(
            name: BlogCommentSaveRouteExtension::NAME,
            extension: new BlogCommentSaveRouteExtension($data, $context),
            function: $this->saveComment(...),
        );
    }

    /**
     * The write itself.
     *
     * Private on purpose: the public method is the extension point and nothing else, so a listener can
     * replace the whole operation without having to re-implement half of it. A route body visible from
     * outside would invite a decorator, and a decorator is what this indirection replaces.
     *
     * @throws BlogException when the shop switched comments off or the entry is not readable
     * @throws ConstraintViolationException when the payload does not have the shape a comment needs
     */
    private function saveComment(RequestDataBag $data, SalesChannelContext $context): NoContentResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        if (!$this->settings->areCommentsEnabled($salesChannelId)) {
            throw BlogException::commentsDisabled();
        }

        EmailIdnConverter::encodeDataBag($data);

        $this->validate($data, $context->getContext());

        $blogId = (string) $data->get('blogId');
        $this->assertBlogIsReadable($blogId, $context);

        $customer = $context->getCustomer();

        $draft = new BlogCommentDraft(
            blogId: $blogId,
            parentId: $this->parentId($data),
            content: trim((string) $data->get('content')),
            title: $this->optional($data, 'title'),
            customerId: $customer?->getId(),
            externalUser: $this->optional($data, 'externalUser'),
            externalEmail: $this->optional($data, 'externalEmail'),
            salesChannelId: $salesChannelId,
            languageId: $context->getLanguageId(),
        );

        $this->eventDispatcher->dispatch(new BlogCommentWriteEvent($draft, $context));

        $this->commentService->create($draft, $context->getContext());

        return new NoContentResponse();
    }

    /**
     * @throws BlogException when the entry does not exist or is not published yet
     */
    private function assertBlogIsReadable(string $blogId, SalesChannelContext $context): void
    {
        $criteria = new Criteria([$blogId]);
        $criteria->setLimit(1);

        $blog = $this->blogRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$blog instanceof BlogEntity || !$this->visibilityGuard->isVisible($blog, $context->getContext())) {
            throw BlogException::notFound($blogId);
        }
    }

    /**
     * The same shape the product review enforces, minus the rating: a name the moderator can address,
     * a reachable address for the flood limit, and a body that is more than a keystroke.
     *
     * @throws ConstraintViolationException
     */
    private function validate(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('blog.comment.create');

        $definition->add('blogId', new NotBlank(), new EntityExists(entity: 'blog', context: $context));
        $definition->add('externalUser', new NotBlank(), new Length(max: 255));
        $definition->add('externalEmail', new NotBlank(), new Email(), new Length(max: 255));
        $definition->add('title', new Length(max: 255));
        $definition->add('content', new NotBlank(), new Length(min: 10));

        $this->validator->validate($data->all(), $definition);

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    private function parentId(RequestDataBag $data): ?string
    {
        return $this->optional($data, 'parentId');
    }

    private function optional(RequestDataBag $data, string $key): ?string
    {
        $value = $data->get($key);

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
