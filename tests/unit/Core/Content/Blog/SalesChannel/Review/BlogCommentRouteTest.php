<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\SalesChannel\Review;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Event\BlogCommentWriteEvent;
use Shopwell\Core\Content\Blog\SalesChannel\Review\BlogCommentRoute;
use Shopwell\Core\Content\Blog\Service\BlogCommentFloodGuard;
use Shopwell\Core\Content\Blog\Service\BlogCommentService;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

/**
 * The store-api route is reachable without a browser, so it is the place where the shop switches and
 * the payload meet. Two promises matter: a shop that switched comments off refuses before anything is
 * validated, and the status of a created comment comes from the configuration, never from the
 * request - otherwise a crafted payload could publish itself.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogCommentRoute::class)]
class BlogCommentRouteTest extends TestCase
{
    public function testAShopThatSwitchedCommentsOffRefusesTheWrite(): void
    {
        $settings = static::createStub(BlogSettings::class);
        $settings->method('areCommentsEnabled')->willReturn(false);

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->never())->method('validate');

        $route = $this->route(settings: $settings, validator: $validator);

        $this->expectExceptionObject(BlogException::commentsDisabled());

        $route->save($this->payload(), Generator::generateSalesChannelContext());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntryAVisitorMayNotReadCannotBeCommentedOn(): void
    {
        /** @var EntityRepository<BlogCollection>&Stub $blogRepository */
        $blogRepository = static::createStub(EntityRepository::class);
        $blogRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                BlogEntity::ENTITY_NAME,
                0,
                new BlogCollection(),
                null,
                $criteria,
                $context,
            )
        );

        $route = $this->route(blogRepository: $blogRepository);

        $this->expectExceptionObject(BlogException::notFound('blog-id'));

        $route->save($this->payload(), Generator::generateSalesChannelContext());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheCreatedCommentIsHeldBackWhenTheShopModerates(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $blog = new BlogEntity();
        $blog->id = 'blog-id';
        $blog->setUniqueIdentifier('blog-id');

        /** @var EntityRepository<BlogCollection>&Stub $blogRepository */
        $blogRepository = static::createStub(EntityRepository::class);
        $blogRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                BlogEntity::ENTITY_NAME,
                1,
                new BlogCollection([$blog]),
                null,
                $criteria,
                $context,
            )
        );

        $visibilityGuard = static::createStub(BlogVisibilityGuard::class);
        $visibilityGuard->method('isVisible')->willReturn(true);

        $settings = static::createStub(BlogSettings::class);
        $settings->method('areCommentsEnabled')->willReturn(true);
        $settings->method('autoPublishComments')->willReturn(false);

        /** @var EntityRepository<BlogCommentCollection>&MockObject $commentRepository */
        $commentRepository = $this->createMock(EntityRepository::class);
        $commentRepository->method('searchIds')->willReturn(
            IdSearchResult::fromIds([], new Criteria(), $salesChannelContext->getContext())
        );

        $created = null;
        $commentRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $payload) use (&$created): bool {
                $created = $payload;

                return true;
            }))
            ->willReturn(static::createStub(EntityWrittenContainerEvent::class));

        $written = null;
        $eventDispatcher = $this->createMock(ContractsEventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$written): object {
                $written = $event;

                return $event;
            });

        $route = $this->route(
            blogRepository: $blogRepository,
            commentRepository: $commentRepository,
            settings: $settings,
            visibilityGuard: $visibilityGuard,
            eventDispatcher: $eventDispatcher,
        );

        $response = $route->save($this->payload(), $salesChannelContext);

        static::assertSame(204, $response->getStatusCode());
        static::assertInstanceOf(BlogCommentWriteEvent::class, $written);

        $draft = $written->getDraft();
        static::assertSame('blog-id', $draft->blogId);
        static::assertSame('A comment that is long enough', $draft->content);
        static::assertNull($draft->parentId);
        static::assertSame('reader@example.com', $draft->externalEmail);
        static::assertSame($salesChannelContext->getSalesChannelId(), $draft->salesChannelId);

        static::assertIsArray($created);
        static::assertSame('blog-id', $created[0]['blogId']);
        static::assertSame('A comment that is long enough', $created[0]['content']);
        static::assertFalse($created[0]['status']);
        static::assertSame($salesChannelContext->getSalesChannelId(), $created[0]['salesChannelId']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAParentCommentIsStoredWithItsParent(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $blog = new BlogEntity();
        $blog->id = 'blog-id';
        $blog->setUniqueIdentifier('blog-id');

        $parent = new BlogCommentEntity();
        $parent->id = 'parent-id';
        $parent->setUniqueIdentifier('parent-id');
        $parent->blogId = 'blog-id';
        $parent->status = true;

        /** @var EntityRepository<BlogCollection>&Stub $blogRepository */
        $blogRepository = static::createStub(EntityRepository::class);
        $blogRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                BlogEntity::ENTITY_NAME,
                1,
                new BlogCollection([$blog]),
                null,
                $criteria,
                $context,
            )
        );

        $visibilityGuard = static::createStub(BlogVisibilityGuard::class);
        $visibilityGuard->method('isVisible')->willReturn(true);

        $settings = static::createStub(BlogSettings::class);
        $settings->method('areCommentsEnabled')->willReturn(true);

        /** @var EntityRepository<BlogCommentCollection>&MockObject $commentRepository */
        $commentRepository = $this->createMock(EntityRepository::class);
        $commentRepository->method('searchIds')->willReturn(
            IdSearchResult::fromIds([], new Criteria(), $salesChannelContext->getContext())
        );
        $commentRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                BlogCommentEntity::ENTITY_NAME,
                1,
                new BlogCommentCollection([$parent]),
                null,
                $criteria,
                $context,
            )
        );
        $commentRepository->expects($this->once())->method('create');

        $route = $this->route(
            blogRepository: $blogRepository,
            commentRepository: $commentRepository,
            settings: $settings,
            visibilityGuard: $visibilityGuard,
        );

        $route->save($this->payload(['parentId' => 'parent-id']), $salesChannelContext);
    }

    /**
     * @param EntityRepository<BlogCollection>|null $blogRepository
     * @param EntityRepository<BlogCommentCollection>|null $commentRepository
     */
    private function route(
        ?EntityRepository $blogRepository = null,
        ?EntityRepository $commentRepository = null,
        ?DataValidator $validator = null,
        ?BlogSettings $settings = null,
        ?BlogVisibilityGuard $visibilityGuard = null,
        ?ContractsEventDispatcherInterface $eventDispatcher = null,
    ): BlogCommentRoute {
        $commentRepository ??= static::createStub(EntityRepository::class);

        if ($settings === null) {
            $settings = static::createStub(BlogSettings::class);
            $settings->method('areCommentsEnabled')->willReturn(true);
        }

        $floodGuard = new BlogCommentFloodGuard(
            $commentRepository,
            $this->clock(),
        );

        $validator ??= $this->validatorThatPasses();

        return new BlogCommentRoute(
            $blogRepository ?? static::createStub(EntityRepository::class),
            $validator,
            new BlogCommentService($commentRepository, $floodGuard, $settings),
            $settings,
            $visibilityGuard ?? static::createStub(BlogVisibilityGuard::class),
            $eventDispatcher ?? static::createStub(ContractsEventDispatcherInterface::class),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
        );
    }

    private function validatorThatPasses(): DataValidator
    {
        $validator = $this->createMock(DataValidator::class);
        $validator->method('validate');
        $validator->method('getViolations')->willReturn(new ConstraintViolationList());

        return $validator;
    }

    private function clock(): ClockInterface
    {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01 10:00:00', new \DateTimeZone('UTC')));

        return $clock;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function payload(array $overrides = []): RequestDataBag
    {
        return new RequestDataBag(array_merge([
            'blogId' => 'blog-id',
            'externalUser' => 'Reader',
            'externalEmail' => 'reader@example.com',
            'content' => 'A comment that is long enough',
        ], $overrides));
    }
}
