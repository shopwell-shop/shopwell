<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App;

use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * Helpers for testing app lifecycle components in unit tests
 *
 * @internal
 */
final class AppFixture
{
    private function __construct()
    {
    }

    public static function createAppEntity(string $name = 'testApp', ?string $id = null, bool $active = true, bool $allowDisable = true): AppEntity
    {
        $app = new AppEntity();
        $app->setId($id ?? Uuid::randomHex());
        $app->setName($name);
        $app->setLabel($name);
        $app->setPath($name);
        $app->setActive($active);
        $app->setAllowDisable($allowDisable);
        $app->setVersion('1.0.0');
        $app->setIntegrationId('integration-id');
        $app->setAclRoleId('acl-role-id');
        $app->setSourceType('static');
        $app->setCreatedAt(new \DateTimeImmutable('2026-01-01 00:00:00'));

        return $app;
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    public static function createAppRepository(AppEntity ...$apps): StaticEntityRepository
    {
        $repository = new StaticEntityRepository([new AppCollection($apps)]);

        return $repository;
    }

    /**
     * @return StaticEntityRepository<LanguageCollection>
     */
    public static function createLanguageRepository(string $locale = 'en-GB'): StaticEntityRepository
    {
        $localeEntity = new LocaleEntity();
        $localeEntity->assign(['code' => $locale]);

        $languageEntity = new LanguageEntity();
        $languageEntity->assign([
            'id' => 'language-id',
            'translationCode' => $localeEntity,
        ]);

        $repository = new StaticEntityRepository([new LanguageCollection([$languageEntity])]);

        return $repository;
    }

    public static function createInstallContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppPersistContext {
        return self::createPersistContext($app, $manifest, $appFilesystem ?? new StaticFilesystem(), $defaultLocale);
    }

    public static function createUpdateContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppPersistContext {
        return self::createPersistContext($app, $manifest, $appFilesystem ?? new StaticFilesystem(), $defaultLocale);
    }

    private static function createPersistContext(
        AppEntity $app,
        Manifest $manifest,
        Filesystem $fs,
        string $defaultLocale
    ): AppPersistContext {
        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $fs,
            defaultLocale: $defaultLocale,
        );
    }
}
