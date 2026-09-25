<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\Docs\Script\ScriptReferenceDataCollector;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptReferenceDataCollector::class)]
class ScriptReferenceDataCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        ScriptReferenceDataCollector::reset();
        parent::tearDown();
    }

    public function testSetAndGetShopwellClasses(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([\stdClass::class, \Countable::class]);

        static::assertSame([\stdClass::class, \Countable::class], ScriptReferenceDataCollector::getShopwellClasses());
    }

    public function testSetAndGetFiles(): void
    {
        $file = static::createStub(SplFileInfo::class);
        ScriptReferenceDataCollector::setFiles(['foo.php' => $file]);

        static::assertSame(['foo.php' => $file], ScriptReferenceDataCollector::getFiles());
    }

    public function testResetClearsClasses(): void
    {
        ScriptReferenceDataCollector::setShopwellClasses([\stdClass::class]);
        ScriptReferenceDataCollector::reset();

        // After reset, setShopwellClasses with new data must be accepted
        ScriptReferenceDataCollector::setShopwellClasses([\Countable::class]);
        static::assertSame([\Countable::class], ScriptReferenceDataCollector::getShopwellClasses());
    }

    public function testResetClearsFiles(): void
    {
        $file = static::createStub(SplFileInfo::class);
        ScriptReferenceDataCollector::setFiles(['foo.php' => $file]);
        ScriptReferenceDataCollector::reset();

        // After reset, setFiles with new data must be accepted
        $newFile = static::createStub(SplFileInfo::class);
        ScriptReferenceDataCollector::setFiles(['bar.php' => $newFile]);
        static::assertSame(['bar.php' => $newFile], ScriptReferenceDataCollector::getFiles());
    }

    public function testGetShopwellClassesLoadsRealClassesWhenNotSet(): void
    {
        // Point scan at a small fixture directory to avoid full codebase scan
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');

        $classes = ScriptReferenceDataCollector::getShopwellClasses();

        static::assertIsArray($classes);
        static::assertNotEmpty($classes);
        foreach ($classes as $class) {
            static::assertIsString($class);
        }
    }

    public function testGetShopwellClassesIsCachedAfterFirstCall(): void
    {
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');

        $first = ScriptReferenceDataCollector::getShopwellClasses();
        $second = ScriptReferenceDataCollector::getShopwellClasses();

        static::assertSame($first, $second);
    }

    public function testGetFilesLoadsRealFilesWhenNotSet(): void
    {
        // Point finder at a small fixture directory to avoid full codebase scan
        ScriptReferenceDataCollector::setFinderPaths([__DIR__ . '/_fixtures']);

        $files = ScriptReferenceDataCollector::getFiles();

        static::assertIsArray($files);
        static::assertNotEmpty($files);
    }

    public function testGetFilesIsCachedAfterFirstCall(): void
    {
        ScriptReferenceDataCollector::setFinderPaths([__DIR__ . '/_fixtures']);

        $first = ScriptReferenceDataCollector::getFiles();
        $second = ScriptReferenceDataCollector::getFiles();

        static::assertSame($first, $second);
    }

    public function testResetAlsoClearsScanPathAndFinderPaths(): void
    {
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');
        ScriptReferenceDataCollector::setFinderPaths([__DIR__ . '/_fixtures']);
        ScriptReferenceDataCollector::reset();

        // After reset, setScanPath with a new path must work
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');
        $classes = ScriptReferenceDataCollector::getShopwellClasses();
        static::assertIsArray($classes);
    }
}
