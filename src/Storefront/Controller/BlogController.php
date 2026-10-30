<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\SalesChannel\Review\BlogCommentRoute;
use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Content\Blog\Service\PostTypeHandler;
use Shopwell\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Blog\BlogDetailPage;
use Shopwell\Storefront\Page\Blog\BlogDetailPageLoadedHook;
use Shopwell\Storefront\Page\Blog\BlogPageLoadedHook;
use Shopwell\Storefront\Page\Blog\BlogPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The public pages of the blog and the form that writes a comment.
 *
 * The controller holds no rule of its own: the page loader decides what a page contains and the guard
 * decides what may be seen. The one decision left here is which template renders an entry, and that is
 * asked from the type registry so a plugin type renders through its own handler.
 *
 * The comment action is a storefront action that calls the store-api route. A form without javascript
 * has to survive a redirect and a flash message, and the store-api route answers a plain request with
 * a json body - but the validation itself must not exist twice, so the route object is called instead
 * of duplicated.
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use the page loader.
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class BlogController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogPageLoader $pageLoader,
        private readonly BlogTypeRegistry $typeRegistry,
        private readonly BlogCommentRoute $commentRoute,
        private readonly ConstraintViolationTranslator $constraintViolationTranslator,
    ) {
    }

    #[Route(
        path: '/blog',
        name: 'frontend.blog.index',
        options: ['seo' => false],
        methods: [Request::METHOD_GET],
    )]
    public function indexAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->load($request, $context);

        $this->hook(new BlogPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/blog/index.html.twig', ['page' => $page]);
    }

    /**
     * A category listing under its own readable url.
     *
     * The same page as the filter query parameter, with one difference that matters for search
     * engines: the category is part of the path, so the listing has a canonical address, a title of its
     * own and a place in the sitemap. `/blog?category=…` has none of the three.
     */
    #[Route(
        path: '/blog/category/{blogCategoryId}',
        name: 'frontend.blog.category',
        requirements: ['blogCategoryId' => '[0-9a-f]{32}'],
        options: ['seo' => false],
        methods: [Request::METHOD_GET],
    )]
    public function categoryAction(string $blogCategoryId, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->load($request, $context, $blogCategoryId);

        $this->hook(new BlogPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/blog/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/blog/{blogId}',
        name: 'frontend.blog.detail',
        requirements: ['blogId' => '[0-9a-f]{32}'],
        options: ['seo' => false],
        methods: [Request::METHOD_GET],
    )]
    public function detailAction(string $blogId, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->loadDetail($blogId, $request, $context);

        $this->hook(new BlogDetailPageLoadedHook($page, $context));

        return $this->renderStorefront($this->template($page), ['page' => $page]);
    }

    /**
     * Takes the comment form of the detail page.
     *
     * The flash carries a snippet key and not a sentence, so the message is translated when it is
     * rendered and not when the request that produced it ran. A visitor who switched the language
     * between submitting and reading sees their own language.
     */
    #[Route(
        path: '/blog/comment',
        name: 'frontend.blog.comment.save',
        options: ['seo' => false],
        methods: [Request::METHOD_POST],
    )]
    public function saveCommentAction(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $blogId = (string) $data->get('blogId');

        try {
            $this->commentRoute->save($data, $context);

            $this->addFlash('success', 'blog.comments.form.success');
        } catch (ConstraintViolationException $exception) {
            // the message of a violation is a machine readable code, not a sentence: it is translated
            // here so the visitor reads the reason in the language the form was rendered in
            foreach ($exception->getViolations() as $violation) {
                $this->addFlash('danger', $this->constraintViolationTranslator->translate($violation));
            }
        } catch (BlogException $exception) {
            $this->addFlash(
                'danger',
                $exception->getErrorCode() === BlogException::COMMENT_FLOOD
                    ? 'blog.comments.form.flood'
                    : 'blog.comments.form.error'
            );
        }

        return $this->backToEntry($blogId);
    }

    /**
     * Redirects back into the thread the visitor came from.
     *
     * A blog id that is not a uuid never reached the database - the validation in the route rejected it
     * before - so redirecting to it would produce a second error page on top of the first. The listing
     * is the honest answer for a request that identifies no entry.
     */
    private function backToEntry(string $blogId): RedirectResponse
    {
        if (preg_match('/^[0-9a-f]{32}\z/', $blogId) !== 1) {
            return $this->redirectToRoute('frontend.blog.index');
        }

        return new RedirectResponse(
            $this->generateUrl('frontend.blog.detail', ['blogId' => $blogId]) . '#comments',
            Response::HTTP_FOUND,
            ['X-Robots-Tag' => 'noindex']
        );
    }

    /**
     * The template of the entry's type.
     *
     * An entry whose type nobody claims - the plugin that owned the type was deactivated - falls back
     * to the article layout instead of answering with an error page. The content is written and the
     * url is public, so a generic layout is strictly better than a 500 for a visitor who followed a
     * link. The administration and every write path keep the loud exception.
     */
    private function template(BlogDetailPage $page): string
    {
        $blog = $page->getBlog();

        if ($blog === null) {
            return PostTypeHandler::DETAIL_TEMPLATE;
        }

        return $this->typeRegistry->find($blog->type)?->getDetailTemplate()
            ?? PostTypeHandler::DETAIL_TEMPLATE;
    }
}
