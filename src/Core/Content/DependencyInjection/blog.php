<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogVideo\BlogVideoEntity;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\SalesChannel\Review\BlogCommentRoute;
use Shopwell\Core\Content\Blog\Service\BlogCommentFloodGuard;
use Shopwell\Core\Content\Blog\Service\BlogCommentService;
use Shopwell\Core\Content\Blog\Service\BlogCommentTree;
use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Blog\Service\BlogReadingTimeEstimator;
use Shopwell\Core\Content\Blog\Service\BlogService;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Content\Blog\Service\PostTypeHandler;
use Shopwell\Core\Content\Blog\Service\VideoTypeHandler;
use Shopwell\Core\Content\Blog\Sitemap\BlogUrlProvider;
use Shopwell\Core\Content\Cms\DataResolver\Element\BlogListingCmsElementResolver;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The blog domain, without a single storefront class.
 *
 * Everything that renders a page lives in `src/Storefront/DependencyInjection/` - the controller, the
 * page objects and the two seo url routes. The split is not cosmetic: core is installable without a
 * storefront, so a core service that references `Shopwell\Storefront\*` would make the whole domain
 * unusable on a headless installation. What stays here is the data side: the entities, the readers
 * that answer "which entries belong to this page", the cms element, the write route for comments and
 * the sitemap.
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // ---- the entries, the categories they are filed in and the authors that sign them ----
    // The entities are attribute based, the `shopwell.entity` tag is what AttributeEntityCompilerPass
    // reads to build the AttributeEntityDefinition and the repository (`<entity_name>.repository`).
    // The translated columns (blog_translation, blog_category_translation and
    // blog_author_translation) are derived from the `translated` fields plus the `translations`
    // association of those entities, they are not registered here.
    $services->set(BlogEntity::class)
        ->tag('shopwell.entity');

    $services->set(BlogCategoryEntity::class)
        ->tag('shopwell.entity');

    $services->set(BlogAuthorEntity::class)
        ->tag('shopwell.entity');

    $services->set(BlogCategoryAssignmentEntity::class)
        ->tag('shopwell.entity');

    $services->set(BlogVideoEntity::class)
        ->tag('shopwell.entity');

    // The comments are a full entity and not a column on the entry: a thread has its own pagination,
    // its own moderation status and its own write route, and all three need a repository of their own.
    $services->set(BlogCommentEntity::class)
        ->tag('shopwell.entity');

    // ---- how a blog type is rendered ----
    // Every handler tags itself, so a plugin adds a type without touching the core. The registry is
    // the only consumer of the tag and the only place that knows the order.
    $services->set(PostTypeHandler::class)
        ->tag('shopwell.blog_type_handler');

    $services->set(VideoTypeHandler::class)
        ->tag('shopwell.blog_type_handler');

    $services->set(BlogTypeRegistry::class)
        ->args([
            tagged_iterator('shopwell.blog_type_handler'),
        ]);

    // ---- whether a visitor may see an entry ----
    $services->set(BlogVisibilityGuard::class)
        ->args([
            service(ClockInterface::class),
        ]);

    $services->set(BlogSettings::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    // ---- taking an entry live and scheduling it ----
    $services->set(BlogService::class)
        ->args([
            service('blog.repository'),
            service(ClockInterface::class),
        ]);

    // ---- the comment thread ----
    $services->set(BlogCommentFloodGuard::class)
        ->args([
            service('blog_comment.repository'),
            service(ClockInterface::class),
        ]);

    // The tree builder is stateless, so it is a plain service and not a struct that gets rebuilt per
    // request. It is registered although nothing injects it by class name: the detail reader and the
    // storefront page loader receive it by id.
    $services->set(BlogCommentTree::class);

    $services->set(BlogCommentService::class)
        ->args([
            service('blog_comment.repository'),
            service(BlogCommentFloodGuard::class),
            service(BlogSettings::class),
        ]);

    // ---- reading a page of the blog ----
    // One estimator, two readers. The listing and the article both report a reading time, and a
    // reader compares the two numbers, so the arithmetic must not exist twice.
    $services->set(BlogReadingTimeEstimator::class)
        ->args([
            service(HtmlSanitizer::class),
        ]);

    $services->set(BlogListingReader::class)
        ->args([
            service('blog.repository'),
            service('blog_category.repository'),
            service('blog_category_assignment.repository'),
            service('media.repository'),
            service(BlogVisibilityGuard::class),
            service(BlogSettings::class),
            service(BlogReadingTimeEstimator::class),
        ]);

    $services->set(BlogDetailReader::class)
        ->args([
            service('blog.repository'),
            service('blog_comment.repository'),
            service('media.repository'),
            service(BlogVisibilityGuard::class),
            service(BlogSettings::class),
            service(BlogCommentTree::class),
            service(BlogReadingTimeEstimator::class),
            service(HtmlSanitizer::class),
        ]);

    // The element renders the same listing as the page, so it reads through the same reader. The tag
    // is the one CmsSlotsDataResolver collects, which is why the resolver is registered here and not
    // in cms.php - the blog owns its element, the cms only reads the tag.
    $services->set(BlogListingCmsElementResolver::class)
        ->args([
            service(BlogListingReader::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    // ---- writing a comment ----
    // The route is the single validation surface: the storefront form and the store-api endpoint both
    // run through it, so the two can not drift apart.
    $services->set(BlogCommentRoute::class)
        ->args([
            service('blog.repository'),
            service(DataValidator::class),
            service(BlogCommentService::class),
            service(BlogSettings::class),
            service(BlogVisibilityGuard::class),
            service(EventDispatcherInterface::class),
            service(ExtensionDispatcher::class),
        ])
        ->public();

    // ---- the sitemap ----
    // The provider stays in core although the urls it writes are storefront urls: it asks
    // EntityRouteResolver for the route name instead of importing the storefront route class, so a
    // shop without a storefront still generates a sitemap of translated slugs.
    $services->set(BlogUrlProvider::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(EntityRouteResolver::class),
        ])
        ->tag('shopwell.sitemap_url_provider')
        ->public();
};
