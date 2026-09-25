<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Plugin\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginException;
use Shopwell\Core\Framework\Plugin\Util\ZipUtils;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ZipUtils::class)]
class ZipUtilsTest extends TestCase
{
    public function testExceptionIsThrownIfZipFileDoesNotExist(): void
    {
        $this->expectExceptionObject(PluginException::cannotExtractNoSuchFile('/some/file/that/does/not/exist.zip'));

        ZipUtils::openZip('/some/file/that/does/not/exist.zip');
    }

    public function testExceptionIsThrownIfZipIsInvalid(): void
    {
        $this->expectExceptionObject(PluginException::cannotExtractInvalidZipFile(__FILE__));

        ZipUtils::openZip(__FILE__);
    }

    public function testArchiveIsReturnedForValidZip(): void
    {
        $archive = ZipUtils::openZip(
            __DIR__ . '/../../../../../../tests/integration/Core/Framework/Plugin/_fixtures/archives/App.zip'
        );

        try {
            static::assertSame(21, $archive->numFiles);
        } finally {
            $archive->close();
        }
    }
}
