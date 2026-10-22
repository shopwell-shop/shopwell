<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\SalesChannel\Review\BlogCommentRoute;
use Shopwell\Core\Content\Blog\Service\BlogCommentFloodGuard;
use Shopwell\Core\Content\Blog\Service\BlogCommentService;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\BlogController;
use Shopwell\Storefront\Page\Blog\BlogPageLoader;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The form without javascript depends on two redirects that are easy to break: it always returns to
 * the thread the visitor came from, and it never redirects to a blog id that was rejected before it
 * reached the database - that would produce a second error page on top of the first. The flash
 * carries a snippet key, so the message is translated when it is rendered and not when it was
 * produced.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogController::class)]
class BlogControllerTest extends TestCase
{
    private const BLOG_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testARefusedCommentReturnsToTheThreadWithTheFloodMessage(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->controller($session, $this->floodingCommentRoute(self::BLOG_ID), '/blog/' . self::BLOG_ID);

        $response = $controller->saveCommentAction(
            new RequestDataBag([
                'blogId' => self::BLOG_ID,
                'externalEmail' => 'reader@example.com',
            ]),
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/blog/' . self::BLOG_ID . '#comments', $response->getTargetUrl());
        static::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        static::assertSame(['blog.comments.form.flood'], $session->getFlashBag()->get('danger'));
    }

    public function testAnUnknownBlogIdFallsBackToTheListingInsteadOfRedirectingToIt(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->controller($session, $this->disabledCommentRoute(), '/blog');

        $response = $controller->saveCommentAction(
            new RequestDataBag(['blogId' => 'not-a-uuid']),
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/blog', $response->getTargetUrl());
        static::assertSame(['blog.comments.form.error'], $session->getFlashBag()->get('danger'));
    }

    public function testASuccessfulCommentRedirectsBackAndConfirmedWithASnippetKey(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $controller = $this->controller($session, $this->acceptingCommentRoute(self::BLOG_ID), '/blog/' . self::BLOG_ID);

        $response = $controller->saveCommentAction(
            new RequestDataBag(['blogId' => self::BLOG_ID]),
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/blog/' . self::BLOG_ID . '#comments', $response->getTargetUrl());
        static::assertSame(['blog.comments.form.success'], $session->getFlashBag()->get('success'));
    }

    private function controller(Session $session, BlogCommentRoute $commentRoute, string $detailUrl): BlogController
    {
        $controller = new BlogController(
            static::createStub(BlogPageLoader::class),
            new BlogTypeRegistry([]),
            $commentRoute,
            new ConstraintViolationTranslator(static::createStub(TranslatorInterface::class)),
        );

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => $route === 'frontend.blog.index' ? '/blog' : $detailUrl
        );

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(ContractsEventDispatcherInterface::class));

        $controller->setContainer($container);

        return $controller;
    }

    private function floodingCommentRoute(string $blogId): BlogCommentRoute
    {
        $commentRepository = static::createStub(EntityRepository::class);
        $commentRepository->method('searchIds')->willReturn(IdSearchResult::fromIds(
            ['comment-id'],
            new Criteria(),
            Context::createDefaultContext(),
        ));

        return $this->commentRoute($blogId, $commentRepository);
    }

    private function acceptingCommentRoute(string $blogId): BlogCommentRoute
    {
        $commentRepository = $this->createMock(EntityRepository::class);
        $commentRepository->method('searchIds')->willReturn(IdSearchResult::fromIds(
            [],
            new Criteria(),
            Context::createDefaultContext(),
        ));
        $commentRepository->expects($this->once())->method('create');

        return $this->commentRoute($blogId, $commentRepository);
    }

    private function disabledCommentRoute(): BlogCommentRoute
    {
        $settings = static::createStub(BlogSettings::class);
        $settings->method('areCommentsEnabled')->willReturn(false);

        return new BlogCommentRoute(
            $this->createMock(EntityRepository::class),
            $this->passingValidator(),
            new BlogCommentService(
                static::createStub(EntityRepository::class),
                new BlogCommentFloodGuard(static::createStub(EntityRepository::class), $this->clock()),
                $settings,
            ),
            $settings,
            static::createStub(BlogVisibilityGuard::class),
            static::createStub(ContractsEventDispatcherInterface::class),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
        );
    }

    /**
     * @param EntityRepository<BlogCommentCollection> $commentRepository
     */
    private function commentRoute(string $blogId, EntityRepository $commentRepository): BlogCommentRoute
    {
        $blog = new BlogEntity();
        $blog->id = $blogId;
        $blog->setUniqueIdentifier($blogId);

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
        $settings->method('autoPublishComments')->willReturn(true);

        return new BlogCommentRoute(
            $blogRepository,
            $this->passingValidator(),
            new BlogCommentService(
                $commentRepository,
                new BlogCommentFloodGuard($commentRepository, $this->clock()),
                $settings,
            ),
            $settings,
            $visibilityGuard,
            static::createStub(ContractsEventDispatcherInterface::class),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
        );
    }

    private function passingValidator(): DataValidator
    {
        $validator = static::createStub(DataValidator::class);
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
}
