<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Deprecation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\ClassAliasMap;
use Shopwell\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClassAliasRegistry::class)]
class ClassAliasRegistryTest extends TestCase
{
    public function testAllRegisteredAliasesAreLoaded(): void
    {
        require \dirname(__DIR__, 5) . '/src/Core/Framework/Deprecation/class_aliases.php';

        foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
            static::assertTrue(class_exists($previousClassName, autoload: false));
            self::assertAliasOf($currentClassName, $previousClassName);
        }
    }

    public function testPackageAliasesAreRegisteredAndExposedToTooling(): void
    {
        $previousClassName = 'Shopwell\\Tests\\Unit\\Core\\Framework\\Deprecation\\LegacyPackageAlias';

        ClassAliasRegistry::registerAliases([
            $previousClassName => self::class,
        ]);

        static::assertTrue(class_exists($previousClassName, autoload: false));
        self::assertAliasOf(self::class, $previousClassName);
        static::assertSame(self::class, ClassAliasRegistry::aliases()[$previousClassName]);
        static::assertSame(self::class, (new ClassAliasMap())->canonicalClassName($previousClassName));
    }

    public function testAliasRegistrationFailsForConflictingClass(): void
    {
        $projectRoot = \dirname(__DIR__, 5);
        $script = <<<'PHP'
namespace Shopwell\Administration\Controller {
    class NotificationController {}
}

namespace {
    try {
        require $argv[1];
        require $argv[2];
    } catch (\LogicException $exception) {
        fwrite(STDERR, $exception->getMessage());

        throw $exception;
    }
}
PHP;

        $process = new Process([
            \PHP_BINARY,
            '-r',
            $script,
            $projectRoot . '/vendor/autoload.php',
            $projectRoot . '/src/Core/Framework/Deprecation/class_aliases.php',
        ]);
        $process->run();

        static::assertFalse($process->isSuccessful());
        static::assertStringContainsString(
            'Cannot register class alias "Shopwell\\Administration\\Controller\\NotificationController" to "Shopwell\\Core\\Framework\\Notification\\Api\\NotificationController": the name already refers to "Shopwell\\Administration\\Controller\\NotificationController".',
            $process->getErrorOutput(),
        );
    }

    /**
     * Two names are subtypes of each other only when they refer to the same class.
     */
    private static function assertAliasOf(string $currentClassName, string $previousClassName): void
    {
        static::assertTrue(is_a($previousClassName, $currentClassName, true), $previousClassName . ' must resolve to ' . $currentClassName);
        static::assertTrue(is_a($currentClassName, $previousClassName, true), $previousClassName . ' must resolve to ' . $currentClassName);
    }
}
