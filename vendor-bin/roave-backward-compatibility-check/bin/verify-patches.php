<?php declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Roave\BackwardCompatibility\Change;
use Roave\BackwardCompatibility\Changes;
use Roave\BackwardCompatibility\CompareClasses;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\ClassBased\ClassBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\InterfaceBased\InterfaceBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\TraitBased\TraitBased;
use Roave\BackwardCompatibility\Factory\ComposerInstallationReflectorFactory;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @return list<string>
 */
function verifyRoavePatches(): array
{
    $toolRoot = dirname(__DIR__);
    $failures = [];
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    $probe = static function (string $name, Closure $check) use (&$failures): void {
        try {
            $check();
        } catch (Throwable $error) {
            $failures[] = $name . ': ' . $error->getMessage();
        }
    };

    $probe('Patch inventory', static function () use ($toolRoot, $assert): void {
        $config = json_decode(file_get_contents($toolRoot . '/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        $installed = json_decode(file_get_contents($toolRoot . '/vendor/composer/installed.json'), true, flags: \JSON_THROW_ON_ERROR);
        $expected = $config['extra']['patches']['roave/backward-compatibility-check'];
        $packages = array_values(array_filter($installed['packages'], static fn (array $package): bool => $package['name'] === 'roave/backward-compatibility-check'));
        $assert(\count($packages) === 1, 'Expected one installed Roave package.');
        $actual = $packages[0]['extra']['patches_applied'] ?? [];
        $assert($actual === $expected, 'Installed Roave patch inventory differs from the configured patches.');
    });

    $filesystem = new Filesystem();
    $directory = sys_get_temp_dir() . '/shopwell-bc-patches-' . bin2hex(random_bytes(12));
    $originalDirectory = getcwd();
    $filesystem->mkdir($directory);
    $canonicalDirectory = realpath($directory);
    if ($canonicalDirectory === false) {
        throw new RuntimeException('Cannot resolve the isolated patch probe directory.');
    }
    $directory = $canonicalDirectory;

    try {
        $filesystem->dumpFile($directory . '/.bc-exclude.php', '<?php return ' . var_export([
            'errors' => ['^\\[BC\\] REMOVED: ExcludedPatchProbe$'],
            'filePatterns' => [$directory . '/Excluded.php'],
        ], true) . ';');
        if (!chdir($directory)) {
            throw new RuntimeException('Cannot enter the isolated patch probe directory.');
        }

        $checks = new class implements ClassBased, InterfaceBased, TraitBased {
            public function __invoke(ReflectionClass $fromClass, ReflectionClass $toClass): Changes
            {
                return Changes::fromList(Change::removed('ExcludedPatchProbe'), Change::removed('KeptPatchProbe'));
            }
        };
        $astLocator = (new BetterReflection())->astLocator();

        $probe('Error exclusion behavior', static function () use ($checks, $astLocator, $assert): void {
            $reflector = new DefaultReflector(new StringSourceLocator('<?php class PatchProbe {}', $astLocator));
            $changes = iterator_to_array((new CompareClasses($checks, $checks, $checks))($reflector, $reflector, $reflector));
            $descriptions = array_map(static fn (Change $change): string => $change->description, $changes);
            $assert($descriptions === ['KeptPatchProbe'], 'The exact excluded change must disappear and the unrelated change must remain.');
        });

        $probe('File exclusion behavior', static function () use ($directory, $filesystem, $astLocator, $assert): void {
            class_exists(ComposerInstallationReflectorFactory::class);
            $filteredClass = 'Roave\\BackwardCompatibility\\Factory\\FilteredSourceLocator';
            $assert(class_exists($filteredClass, false), 'The installed source locator has no exclusion filter.');
            $filesystem->dumpFile($directory . '/Excluded.php', '<?php class ExcludedFilePatchProbe {}');
            $filesystem->dumpFile($directory . '/Kept.php', '<?php class KeptFilePatchProbe {}');
            $sources = new AggregateSourceLocator([
                new SingleFileSourceLocator($directory . '/Excluded.php', $astLocator),
                new SingleFileSourceLocator($directory . '/Kept.php', $astLocator),
            ]);
            $reflector = new DefaultReflector(new $filteredClass($sources));
            $names = [];
            foreach ($reflector->reflectAllClasses() as $class) {
                $names[] = $class->getShortName();
            }
            $assert($names === ['KeptFilePatchProbe'], 'Only the configured file may disappear from the discovered API; observed ' . json_encode($names, \JSON_THROW_ON_ERROR));
        });

        $probe('Experimental exclusion behavior and CLI wiring', static function () use ($toolRoot, $checks, $astLocator, $assert): void {
            $source = file_get_contents($toolRoot . '/vendor/roave/backward-compatibility-check/bin/roave-backward-compatibility-check.php');
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
            $traverser = new NodeTraverser(new NameResolver());
            $ast = $traverser->traverse($ast ?? []);
            $finder = new NodeFinder();
            $class = $finder->findFirst($ast, static fn (Node $node): bool => $node instanceof Node\Stmt\Class_ && $node->name?->toString() === 'ExcludeExperimental');
            $assert($class instanceof Node\Stmt\Class_, 'The installed CLI has no experimental exclusion wrapper.');
            $wrappers = $finder->find($ast, static fn (Node $node): bool => $node instanceof Node\Expr\New_ && $node->class instanceof Node\Name && $node->class->toString() === 'Roave\\ApiCompareCli\\ExcludeExperimental');
            $assert(\count($wrappers) === 6, 'All six CLI comparator groups must use the experimental exclusion wrapper.');

            // Load only the installed wrapper declaration, without running the console application.
            $wrapperClass = 'Roave\\ApiCompareCli\\ExcludeExperimental';
            if (!class_exists($wrapperClass, false)) {
                eval('namespace Roave\\ApiCompareCli; ' . (new Standard())->prettyPrint([$class]));
            }
            $reflector = new DefaultReflector(new StringSourceLocator('<?php /** @experimental */ class ExperimentalPatchProbe {} class StablePatchProbe {}', $astLocator));
            if (!class_exists($wrapperClass, false)) {
                throw new RuntimeException('Cannot load the installed experimental exclusion wrapper.');
            }
            $wrapper = new $wrapperClass($checks);
            if (!$wrapper instanceof ClassBased) {
                throw new RuntimeException('The experimental exclusion wrapper must implement the comparator contract.');
            }
            $experimental = $reflector->reflectClass('ExperimentalPatchProbe');
            $stable = $reflector->reflectClass('StablePatchProbe');
            $assert(\count($wrapper($experimental, $experimental)) === 0, 'Experimental declarations must be excluded.');
            $assert(\count($wrapper($stable, $stable)) === 2, 'Ordinary declarations must retain their changes.');
        });

        $probe('Final annotation wiring and API compatibility', static function () use ($toolRoot, $astLocator, $assert): void {
            $source = file_get_contents($toolRoot . '/vendor/roave/backward-compatibility-check/bin/roave-backward-compatibility-check.php');
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
            $ast = (new NodeTraverser(new NameResolver()))->traverse($ast ?? []);
            $finder = new NodeFinder();
            $wrapperClass = 'Roave\\ApiCompareCli\\SkipSubclassOnlyChecksOnFinalClasses';
            $class = $finder->findFirst($ast, static fn (Node $node): bool => $node instanceof Node\Stmt\Class_ && $node->name?->toString() === 'SkipSubclassOnlyChecksOnFinalClasses');
            $assert($class instanceof Node\Stmt\Class_, 'The installed CLI has no final annotation wrapper.');
            $wrappers = $finder->find($ast, static fn (Node $node): bool => $node instanceof Node\Expr\New_ && $node->class instanceof Node\Name && $node->class->toString() === $wrapperClass);
            $checks = [];
            foreach ($wrappers as $wrapper) {
                if (!$wrapper instanceof Node\Expr\New_) {
                    throw new RuntimeException('Expected a final annotation wrapper construction.');
                }
                $check = $wrapper->args[0]->value;
                if (!$check instanceof Node\Expr\New_ || !$check->class instanceof Node\Name) {
                    throw new RuntimeException('The final annotation wrapper must directly wrap a comparator.');
                }
                $name = $check->class->getLast();
                $checks[$name] = ($checks[$name] ?? 0) + 1;
            }
            ksort($checks);
            $assert($checks === ['ClassBecameFinal' => 1, 'MethodBecameFinal' => 4, 'ParameterTypeChanged' => 4, 'ReturnTypeChanged' => 4], 'All thirteen subclass-only comparator sites must be wrapped, without wrapping caller-facing checks; observed ' . json_encode($checks, \JSON_THROW_ON_ERROR));

            $printer = new Standard();
            if (!class_exists($wrapperClass, false)) {
                eval('namespace Roave\\ApiCompareCli; ' . $printer->prettyPrint([$class]));
            }
            $comparison = $finder->findFirst($ast, static fn (Node $node): bool => $node instanceof Node\Expr\New_ && $node->class instanceof Node\Name && $node->class->toString() === CompareClasses::class);
            if (!$comparison instanceof Node\Expr\New_) {
                throw new RuntimeException('The installed CLI has no API comparator.');
            }
            $compare = eval('return ' . $printer->prettyPrintExpr($comparison) . ';');
            $assert($compare instanceof CompareClasses, 'Cannot load the installed CLI comparator.');
            $reflect = static fn (string $source): DefaultReflector => new DefaultReflector(new StringSourceLocator('<?php ' . $source, $astLocator));
            $assertChanges = static function (string $from, string $to, int $expected, string $case) use ($reflect, $compare, $assert): void {
                $past = $reflect($from);
                $future = $reflect($to);
                $changes = iterator_to_array($compare($past, $past, $future));
                foreach ($changes as $change) {
                    $assert(!$change->isSkipped(), $case . ': comparator failed: ' . $change->description);
                }
                $assert(\count($changes) === $expected, $case . ': expected ' . $expected . ' change(s), observed ' . json_encode(array_map(static fn (Change $change): string => (string) $change, $changes), \JSON_THROW_ON_ERROR));
            };

            foreach (['/** @final */' => 0, 'final' => 0, '' => 1, '/** @finalized */' => 1] as $prefix => $subclassChanges) {
                foreach (['public', 'protected'] as $visibility) {
                    foreach ([
                        ['function probe(): int { return 1; }', 'final function probe(): int { return 1; }'],
                        ['function probe(): int|string { return 1; }', 'function probe(): int { return 1; }'],
                        ['function probe(int $value): void {}', 'function probe(int|string $value): void {}'],
                    ] as [$from, $to]) {
                        $assertChanges($prefix . ' class FinalPatchProbe { ' . $visibility . ' ' . $from . ' }', $prefix . ' class FinalPatchProbe { ' . $visibility . ' ' . $to . ' }', $subclassChanges, $prefix . ' ' . $visibility . ' ' . $from);
                    }
                }
                foreach ([
                    ['function probe(): int { return 1; }', 'function probe(): int|string { return 1; }', 1],
                    ['function probe(int|string $value): void {}', 'function probe(int $value): void {}', 1],
                    ['function probe(): void {}', '', 1],
                ] as [$from, $to, $callerChanges]) {
                    // Open classes report both the caller and subclass type breaks.
                    $expected = $to !== '' ? $callerChanges + $subclassChanges : $callerChanges;
                    $assertChanges($prefix . ' class FinalPatchProbe { public ' . $from . ' }', $prefix . ' class FinalPatchProbe { ' . ($to === '' ? '' : 'public ' . $to) . ' }', $expected, $prefix . ' caller ' . $from);
                }
            }
            $assertChanges('/** @final */ class FinalPatchProbe {}', 'final class FinalPatchProbe {}', 0, 'Documented final class becomes native final');
            $assertChanges('class FinalPatchProbe {}', 'final class FinalPatchProbe {}', 1, 'Open class becomes final');
        });
    } finally {
        if ($originalDirectory !== false) {
            chdir($originalDirectory);
        }
        $filesystem->remove($directory);
    }

    return $failures;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $failures = verifyRoavePatches();
    if ($failures !== []) {
        fwrite(\STDERR, "Roave patch verification failed:\n- " . implode("\n- ", $failures) . "\n");
        exit(1);
    }

    echo "Roave installed patches and exclusion behavior verified.\n";
}
