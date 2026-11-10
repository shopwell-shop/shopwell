<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryTree;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryTreeExtension;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutExtension;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogDetailDataLoader;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogDetailLoaderConfigSerializer;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogLayoutSubscriber;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogListingDataLoader;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogListingLoaderConfigSerializer;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogMappingCandidateProvider;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogSpecificationSource;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\ProvisionBlogLayoutsCommand;
use Shopwell\Core\Content\Blog\Aggregate\BlogVideo\BlogVideoEntity;
use Shopwell\Core\Content\Blog\Api\BlogCategoryTreeController;
use Shopwell\Core\Content\Blog\Api\BlogTypeController;
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
use Shopwell\Core\Content\Blog\Sitemap\BlogUrlProvider;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopwell\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopwell\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    $services->set(BlogCategoryTreeExtension::class)->tag('shopwell.entity.extension');

    $services->set(BlogCategoryTree::class)
        ->args([service(Connection::class), service('blog_category.repository'), service(TreeUpdater::class), service(ChildCountUpdater::class)])
        ->tag('kernel.event_subscriber');

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

    $services->set(BlogContentLayoutDefinition::class)
        ->args([service(ContentLayoutMetadataDeriver::class)])
        ->tag('shopwell.entity.definition');

    $services->set(BlogSpecificationSource::class)
        ->args([
            service('blog_content_layout.repository'),
            service(BlogContentLayoutDefinition::class),
            service(EntityLayoutContextFactory::class),
            service('blog.repository'),
            service(SystemConfigService::class),
            service(EntityLayoutResolver::class),
            service(CacheTagCollector::class),
            service(BlogVisibilityGuard::class),
        ])
        ->tag('content_system.entity_specification_source', ['priority' => 100]);

    $services->set(BlogMappingCandidateProvider::class)
        ->args([service(BlogContentLayoutDefinition::class)])
        ->tag('content_system.mapping_candidate_provider', ['priority' => 100]);

    $services->set(BlogDetailDataLoader::class)
        ->args([service(BlogDetailReader::class)])
        ->tag('content_system.data_loader');

    $services->set(BlogDetailLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(BlogLayoutSubscriber::class)
        ->args([service(LayoutRootSourceReader::class), service(Connection::class), service(CacheInvalidator::class)])
        ->tag('kernel.event_subscriber');

    $services->set(BlogListingDataLoader::class)
        ->args([service(BlogListingReader::class)])
        ->tag('content_system.data_loader');

    $services->set(BlogListingLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(ProvisionBlogLayoutsCommand::class)
        ->args([service(ContentSystemLayoutProvisioner::class), service(SystemConfigService::class)])
        ->tag('console.command');

    $services->set(BlogTypeRegistry::class);

    $services->set(BlogContentLayoutExtension::class)->tag('shopwell.entity.extension');

    $services->set(BlogCategoryTreeController::class)
        ->args([service(BlogCategoryTree::class)])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(BlogTypeController::class)
        ->args([service(BlogTypeRegistry::class)])
        ->public()
        ->tag('controller.service_arguments');

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
