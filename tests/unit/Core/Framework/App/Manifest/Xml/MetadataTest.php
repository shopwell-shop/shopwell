<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopwell\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Metadata::class)]
class MetadataTest extends TestCase
{
    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');
    }

    public function testFromXml(): void
    {
        $metaData = $this->manifest->getMetadata();
        static::assertSame('test', $metaData->getName());
        static::assertSame('Shopwell', $metaData->getAuthor());
        static::assertSame('(c) by Shopwell', $metaData->getCopyright());
        static::assertSame('MIT', $metaData->getLicense());
        static::assertSame('https://test.com/privacy', $metaData->getPrivacy());
        static::assertSame('1.0.0', $metaData->getVersion());
        static::assertSame('icon.png', $metaData->getIcon());

        static::assertSame([
            'en-GB' => 'Swag App Test',
            'zh-CN' => 'Swag 应用测试',
        ], $metaData->getLabel());
        static::assertSame([
            'en-GB' => 'Test for App System',
            'zh-CN' => '应用系统测试',
        ], $metaData->getDescription());
        static::assertSame([
            'en-GB' => 'Following personal information will be processed on Shopwell\'s servers:

- Name
- Billing address
- Order value',
            'zh-CN' => '以下用户数据将在 Shopwell 的服务器上处理：

- 姓名
- 账单地址
- 订单金额',
        ], $metaData->getPrivacyPolicyExtensions());
    }

    public function testFromXmlWithoutDescription(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifestWithoutDescription.xml');

        $metaData = $manifest->getMetadata();

        static::assertSame([
            'en-GB' => 'Swag App Test',
            'zh-CN' => 'Swag 应用测试',
        ], $metaData->getLabel());
        static::assertSame([], $metaData->getDescription());

        $array = $metaData->toArray('en-GB');
        static::assertSame([], $array['description']);
    }

    public function testValidateTranslationsReturnsMissingTranslationErrorIfTranslationIsMissing(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalid-translations-manifest.xml');
        $error = $manifest->getMetadata()->validateTranslations();

        static::assertInstanceOf(MissingTranslationError::class, $error);
        static::assertSame('Missing translations for "Metadata":
- label: zh-CN, fr-FR', $error->getMessage());
    }

    public function testValidateTranslationsReturnsNull(): void
    {
        static::assertNull($this->manifest->getMetadata()->validateTranslations());
    }

    public function testSelfManagedFalseByDefault(): void
    {
        static::assertFalse($this->manifest->getMetadata()->isSelfManaged());
    }

    public function testSetSelfManaged(): void
    {
        $this->manifest->getMetadata()->setSelfManaged(true);

        static::assertTrue($this->manifest->getMetadata()->isSelfManaged());
    }

    public function testSetVersion(): void
    {
        static::assertSame('1.0.0', $this->manifest->getMetadata()->getVersion());

        $this->manifest->getMetadata()->setVersion('2.0.0');

        static::assertSame('2.0.0', $this->manifest->getMetadata()->getVersion());
    }
}
