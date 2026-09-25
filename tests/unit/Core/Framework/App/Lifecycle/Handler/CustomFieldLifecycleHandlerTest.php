<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Lifecycle\Handler\CustomFieldLifecycleHandler;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Core\System\CustomField\CustomFieldSetPersister;
use Shopwell\Core\System\CustomField\CustomFieldXmlLoader;
use Shopwell\Core\System\CustomField\Xml\CustomFields;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldLifecycleHandler::class)]
class CustomFieldLifecycleHandlerTest extends TestCase
{
    private string $tmpDir;

    private SymfonyFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new SymfonyFilesystem();
        $this->tmpDir = sys_get_temp_dir() . '/sw-test-app-' . bin2hex(random_bytes(4));
        $this->fs->mkdir($this->tmpDir . '/Resources');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    public function testPersistWithExternalFile(): void
    {
        $fixtureFile = \dirname(__DIR__, 4) . '/System/CustomField/_fixtures/custom-fields.xml';
        $this->fs->copy($fixtureFile, $this->tmpDir . '/Resources/config/custom-fields.xml');

        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(function (CustomFields $customFields): bool {
                    $sets = $customFields->getCustomFieldSets();

                    return \count($sets) === 2 && $sets[0]->getName() === 'test_set';
                }),
                'app-id-123',
                'TestApp',
                static::isInstanceOf(Context::class)
            );

        $handler = new CustomFieldLifecycleHandler($sharedPersister);
        $handler->install($this->createContext($this->tmpDir));
    }

    public function testPersistWithoutFileAndWithoutManifest(): void
    {
        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(function (CustomFields $customFields): bool {
                    return $customFields->getCustomFieldSets() === [];
                }),
                'app-id-123',
                'TestApp',
                static::isInstanceOf(Context::class)
            );

        $handler = new CustomFieldLifecycleHandler($sharedPersister);
        $handler->install($this->createContext($this->tmpDir));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed together with inline custom-fields support in manifest.xml
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPersistWithInlineManifestCustomFields(): void
    {
        $fixtureFile = \dirname(__DIR__, 4) . '/System/CustomField/_fixtures/custom-fields.xml';
        $inlineCustomFields = CustomFieldXmlLoader::load($fixtureFile);

        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->willReturnCallback(function (CustomFields $customFields, ?string $appId, ?string $extensionName, Context $context) use ($inlineCustomFields): void {
                static::assertSame($inlineCustomFields, $customFields);
                static::assertSame('app-id-123', $appId);
                static::assertSame('TestApp', $extensionName);
            });

        $handler = new CustomFieldLifecycleHandler($sharedPersister);
        $handler->install($this->createContext($this->tmpDir, $inlineCustomFields));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed together with inline custom-fields support in manifest.xml
     */
    public function testPersistWithInlineManifestCustomFieldsThrowsWithMajorFlag(): void
    {
        $fixtureFile = \dirname(__DIR__, 4) . '/System/CustomField/_fixtures/custom-fields.xml';
        $inlineCustomFields = CustomFieldXmlLoader::load($fixtureFile);

        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->never())->method('sync');

        $handler = new CustomFieldLifecycleHandler($sharedPersister);
        $context = $this->createContext($this->tmpDir, $inlineCustomFields);

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Defining custom fields inline in manifest.xml is deprecated, use Resources/config/custom-fields.xml instead.'
        ));

        $handler->install($context);
    }

    private function createContext(string $appDir, ?CustomFields $inlineCustomFields = null): AppPersistContext
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getCustomFields')->willReturn($inlineCustomFields);

        $app = new AppEntity();
        $app->setId('app-id-123');
        $app->setUniqueIdentifier('app-id-123');
        $app->setName('TestApp');

        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new Filesystem($appDir),
            defaultLocale: 'en-GB',
        );
    }
}
