<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Command;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Command\DownloadTranslationCommand;
use Shopwell\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopwell\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopwell\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DownloadTranslationCommand::class)]
class DownloadTranslationCommandTest extends TestCase
{
    /**
     * @var AbstractTranslationLoader&MockObject
     */
    private AbstractTranslationLoader $translationLoader;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(AbstractTranslationLoader::class);
    }

    public function testDownloadsAllConfiguredLocalesWithoutDatabaseActivation(): void
    {
        $this->translationLoader->expects($this->exactly(2))
            ->method('download')
            ->with(static::callback(
                static fn (string $locale): bool => \in_array($locale, ['zh-CN', 'es-ES'], true)
            ));

        $tester = new CommandTester(new DownloadTranslationCommand(
            $this->translationLoader,
            new TranslationConfig(
                new Uri('http://localhost:8000'),
                ['zh-CN', 'es-ES'],
                [],
                new LanguageCollection(),
                new PluginMappingCollection(),
                new Uri('http://localhost:8000/metadata.json'),
                [],
            ),
        ));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }
}
