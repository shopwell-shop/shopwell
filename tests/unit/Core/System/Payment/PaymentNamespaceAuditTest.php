<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;

/**
 * Guards the one mistake a structural refactor of this module leaves behind: a class that moved and a
 * reference somewhere else that still names its old namespace.
 *
 * A dead reference is silent - PHP resolves it only when the line using it executes, so an import of a
 * class that no longer exists costs nothing until an unrelated test reaches it, and the suite that
 * would have reached it is not necessarily the suite the pipeline runs (the migration tests and the
 * checkout side of the payment link are two that are easy to forget).
 *
 * The audit therefore reads every file of the repository instead of the executed part: whatever names
 * a class of this module has to name one that exists on disk.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class PaymentNamespaceAuditTest extends TestCase
{
    /**
     * The namespaces the audit reads: the module itself and everything that may reference it.
     */
    private const SCANNED_DIRECTORIES = ['src', 'tests'];

    public function testEveryReferenceToTheModuleNamesAClassThatExists(): void
    {
        $root = self::root();

        $references = 0;
        $stale = [];

        foreach (self::phpFiles($root) as $file) {
            $code = (string) file_get_contents($file);

            // the regex is the expensive part - most files can not reference the module at all
            if (!str_contains($code, 'System\\Payment')) {
                continue;
            }

            preg_match_all(
                '/\bShopwell\\\\Core\\\\System\\\\Payment\\\\[A-Za-z0-9_\\\\]*[A-Za-z0-9_]/',
                $code,
                $matches
            );

            foreach (array_unique($matches[0]) as $class) {
                // a namespace declaration is no reference to a class
                if (self::isNamespaceDeclaration($code, $class)) {
                    continue;
                }

                ++$references;

                if (!is_file($root . '/src/Core/' . str_replace('\\', '/', substr($class, \strlen('Shopwell\\Core\\'))) . '.php')) {
                    $stale[$class][] = substr($file, \strlen($root) + 1);
                }
            }
        }

        // a broken pattern would pass the audit by finding nothing
        static::assertGreaterThan(100, $references, 'The audit found almost no references, its pattern is broken.');

        $report = [];
        foreach ($stale as $class => $files) {
            $report[] = $class . ' (' . implode(', ', array_unique($files)) . ')';
        }

        static::assertSame([], $report, "These references name a class of the payment module that does not exist:\n" . implode("\n", $report));
    }

    private static function isNamespaceDeclaration(string $code, string $class): bool
    {
        return (bool) preg_match('/^\s*namespace\s+' . preg_quote($class, '/') . '\s*;/m', $code);
    }

    private static function root(): string
    {
        // tests/unit/Core/System/Payment -> the repository
        $root = \dirname(__DIR__, 5);

        static::assertDirectoryExists($root . '/src/Core');

        return $root;
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $root): array
    {
        $files = [];

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
