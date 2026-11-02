<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;

/**
 * Guards the one mistake a class move leaves behind: a reference somewhere else that still names the
 * old namespace.
 *
 * It is silent by nature - PHP resolves an import only when the line using it runs, and an import of a
 * class that no longer exists costs nothing until then. A suite that never executes that line stays
 * green while the class is unreachable, which is how a moved aggregate definition can keep passing
 * every test and still break the first request that touches it.
 *
 * The audit therefore checks the source tree as a whole instead of the executed part: every class that
 * production code names has to exist on disk.
 *
 * Only unambiguous references are read - an import and a static access - because a docblock may name a
 * namespace rather than a class and would be reported without being wrong.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class StaleClassReferenceTest extends TestCase
{
    private const ROOTS = ['Shopwell\\Core\\', 'Shopwell\\Storefront\\'];

    public function testEveryClassNamedByProductionCodeExists(): void
    {
        $root = self::root();

        $references = 0;
        $stale = [];

        foreach (self::phpFiles($root . '/src') as $file) {
            $code = (string) file_get_contents($file);

            foreach (self::namedClasses($code) as $class) {
                ++$references;

                $path = $root . '/src/' . str_replace('\\', '/', substr($class, \strlen('Shopwell\\')));

                // a directory is a namespace import (`use ...\Consent\Definition;` then `Definition\X`),
                // which is the only other thing a `use` statement may name
                if (is_file($path . '.php') || is_dir($path)) {
                    continue;
                }

                $stale[$class][] = substr($file, \strlen($root) + 1);
            }
        }

        // a broken pattern would pass the audit by finding nothing
        static::assertGreaterThan(1000, $references, 'The audit found almost no references, its pattern is broken.');

        $report = [];
        foreach ($stale as $class => $files) {
            $report[] = $class . ' (' . implode(', ', array_unique($files)) . ')';
        }

        static::assertSame([], $report, "These references name a class that does not exist:\n" . implode("\n", $report));
    }

    /**
     * The line this audit was written for: a class of the loyalty domain moved to the membership
     * domain, and the definitions that point at it kept the old namespace for a while.
     */
    public function testTheAuditReadsAnImportOfTheOldMembershipNamespace(): void
    {
        $code = <<<'PHP'
            <?php
            use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanDefinition;
            use Shopwell\Core\Framework\Context;
            use Shopwell\Core\Framework\Log\Package as LogPackage;
            PHP;

        static::assertSame([
            'Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanDefinition',
            'Shopwell\Core\Framework\Context',
            'Shopwell\Core\Framework\Log\Package',
        ], self::namedClasses($code));
    }

    /**
     * @return list<string>
     */
    private static function namedClasses(string $code): array
    {
        $classes = [];

        preg_match_all('/^use\s+(Shopwell\\\\[A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?;/m', $code, $imports);
        foreach ($imports[1] as $class) {
            $classes[$class] = true;
        }

        preg_match_all('/(Shopwell\\\\[A-Za-z0-9_\\\\]+)::/', $code, $accesses);
        foreach ($accesses[1] as $class) {
            $classes[$class] = true;
        }

        $named = [];
        foreach (array_keys($classes) as $class) {
            if (self::isKnownRoot($class)) {
                $named[] = $class;
            }
        }

        return $named;
    }

    private static function isKnownRoot(string $class): bool
    {
        foreach (self::ROOTS as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function root(): string
    {
        // tests/unit/Core/DevOps/StaticAnalyze -> the repository
        $root = \dirname(__DIR__, 5);

        static::assertDirectoryExists($root . '/src/Core');

        return $root;
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
