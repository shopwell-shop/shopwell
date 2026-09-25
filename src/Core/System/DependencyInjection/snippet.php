<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopwell\Core\System\Snippet\Command\DownloadTranslationCommand;
use Shopwell\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopwell\Core\System\Snippet\Command\LintTranslationFilesCommand;
use Shopwell\Core\System\Snippet\Command\ListTranslationsCommand;
use Shopwell\Core\System\Snippet\Command\UpdateTranslationCommand;
use Shopwell\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Shopwell\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Shopwell\Core\System\Snippet\Files\SnippetFileCollection;
use Shopwell\Core\System\Snippet\Files\StorefrontSnippetLifecycleHandler;
use Shopwell\Core\System\Snippet\Files\StorefrontSnippetStorage;
use Shopwell\Core\System\Snippet\SalesChannel\SalesChannelSnippetLoader;
use Shopwell\Core\System\Snippet\SalesChannel\SnippetRoute;
use Shopwell\Core\System\Snippet\ScheduledTask\UpdateTranslationsTask;
use Shopwell\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Shopwell\Core\System\Snippet\Service\AbstractTranslationConfigLoader;
use Shopwell\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopwell\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopwell\Core\System\Snippet\Service\TranslationFilesystemFactory;
use Shopwell\Core\System\Snippet\Service\TranslationLoader;
use Shopwell\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopwell\Core\System\Snippet\Service\TranslationRemover;
use Shopwell\Core\System\Snippet\Service\TranslationUpdater;
use Shopwell\Core\System\Snippet\SnippetDefinition;
use Shopwell\Core\System\Snippet\SnippetFileHandler;
use Shopwell\Core\System\Snippet\SnippetFixer;
use Shopwell\Core\System\Snippet\SnippetValidator;
use Shopwell\Core\System\Snippet\SnippetValidatorInterface;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;
use Shopwell\Core\System\Snippet\Subscriber\CustomFieldSubscriber;
use Shopwell\Core\System\Snippet\Subscriber\LanguageDeletionSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SnippetSetDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SnippetDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SnippetValidatorInterface::class, SnippetValidator::class)
        ->args([
            service(SnippetFileCollection::class),
            service(SnippetFileHandler::class),
            param('kernel.project_dir') . '/',
        ]);

    $services->set(SnippetValidator::class)
        ->args([
            service(SnippetFileCollection::class),
            service(SnippetFileHandler::class),
            param('kernel.project_dir') . '/',
        ]);

    $services->set(StorefrontSnippetStorage::class)
        ->args([
            service('shopwell.filesystem.translation'),
            service(SourceResolver::class),
            service('logger'),
            param('kernel.cache_dir') . '/app-snippets',
        ]);

    $services->set(StorefrontSnippetLifecycleHandler::class)
        ->args([
            service(StorefrontSnippetStorage::class),
            service(CacheInvalidator::class),
            service(Connection::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1400]);

    $services->set(SnippetFixer::class)
        ->args([
            service(SnippetFileHandler::class),
        ]);

    $services->set(ValidateSnippetsCommand::class)
        ->args([
            service(SnippetValidator::class),
            service(SnippetFixer::class),
        ])
        ->tag('console.command');

    $services->set(CountryAgnosticFileLinter::class)
        ->args([
            service(Filesystem::class),
            service('plugin.repository'),
            service('app.repository'),
            inline_service(Finder::class),
        ]);

    $services->set(LintTranslationFilesCommand::class)
        ->args([
            service(CountryAgnosticFileLinter::class),
        ])
        ->tag('console.command');

    $services->set(InstallTranslationCommand::class)
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
            service(TranslationUpdater::class),
        ])
        ->tag('console.command');

    $services->set(DownloadTranslationCommand::class)
        ->args([
            service(AbstractTranslationLoader::class),
            service(TranslationConfig::class),
        ])
        ->tag('console.command');

    $services->set(UpdateTranslationCommand::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set(ListTranslationsCommand::class)
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set('shopwell.translation.client', Client::class)
        ->args([
            [
                'timeout' => 30,
                'connect_timeout' => 5,
            ],
        ]);

    $services->set(TranslationConfigLoader::class)
        ->args([
            service('filesystem'),
            param('shopwell.translation'),
        ]);

    $services->alias(AbstractTranslationConfigLoader::class, TranslationConfigLoader::class);

    $services->set(TranslationConfig::class)
        ->lazy()
        ->public()
        ->factory([service(TranslationConfigLoader::class), 'load']);

    $services->set(TranslationLoader::class)
        ->args([
            service('shopwell.filesystem.translation'),
            service('language.repository'),
            service('locale.repository'),
            service('snippet_set.repository'),
            service('shopwell.translation.client'),
            service(TranslationConfig::class),
            service('event_dispatcher'),
        ]);

    $services->alias(AbstractTranslationLoader::class, TranslationLoader::class);

    $services->set(TranslationMetadataStore::class)
        ->args([
            service(TranslationConfig::class),
            service('shopwell.translation.client'),
            service('shopwell.filesystem.translation'),
            service('cache.object'),
        ]);

    $services->set(TranslationUpdater::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
        ]);

    $services->set(TranslationRemover::class)
        ->args([
            service('shopwell.filesystem.translation'),
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
            service('event_dispatcher'),
        ]);

    $services->set(UpdateTranslationsTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(UpdateTranslationsTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(TranslationUpdater::class),
            service('language.repository'),
        ])
        ->tag('messenger.message_handler');

    $services->set(TranslationFilesystemFactory::class)
        ->args([
            service('shopwell.filesystem.private'),
            service(FilesystemFactory::class),
            param('kernel.project_dir'),
            param('shopwell.translation.use_local_filesystem'),
        ]);

    $services->set('shopwell.filesystem.translation', FilesystemOperator::class)
        ->factory([service(TranslationFilesystemFactory::class), 'create']);

    $services->set(SalesChannelSnippetLoader::class)
        ->args([
            service(Translator::class),
            service(LanguageLocaleCodeProvider::class),
            service('sales_channel.language.repository'),
        ]);

    $services->set(SnippetRoute::class)
        ->public()
        ->args([
            service(SalesChannelSnippetLoader::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SnippetFileHandler::class)
        ->args([
            service('filesystem'),
        ]);

    $services->set(CustomFieldSubscriber::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageDeletionSubscriber::class)
        ->args([
            service(Connection::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('kernel.event_subscriber');
};
