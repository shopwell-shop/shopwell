<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Danger\Struct\PullRequest;
use Shopwell\Core\Framework\Log\Package;

/**
 * Every new class under `src/` needs a matching unit test, unless it is a plain data holder
 * (entity, struct, event, ...), abstract, or excluded from code coverage via the annotation or
 * the phpunit.xml.dist source excludes.
 *
 * @internal
 */
#[Package('framework')]
class MissingUnitTests
{
    private const BASE_TEST_CLASSES = [
        'RuleTestCase',
        'TestCase',
        'MiddlewareTestCase',
        'AbstractProviderTestCase',
        'ImportStrategyTestCase',
        'ConstraintValidatorTestCase',
    ];

    private const PROJECT_TEST_BASES = [
        'Shopwell\\Tests\\Unit\\Core\\Framework\\ContentSystem\\Layout\\Codec\\StoredElementCodecTestCase' => 'tests/unit/Core/Framework/ContentSystem/Layout/Codec/StoredElementCodecTestCase.php',
        'Shopwell\\Tests\\Unit\\Core\\Framework\\ContentSystem\\Layout\\Codec\\StoredTreeConstraintsTestCase' => 'tests/unit/Core/Framework/ContentSystem/Layout/Codec/StoredTreeConstraintsTestCase.php',
    ];

    private const IGNORE_SUFFIXES = [
        'Entity',
        'Collection',
        'Struct',
        'Field',
        'Test',
        'Definition',
        'Event',
        'Exception',
    ];

    public function __construct(private readonly string $phpunitConfigPath = __DIR__ . '/../../../../../../phpunit.xml.dist')
    {
    }

    public function __invoke(Context $context): void
    {
        $addedUnitTests = $context->platform->pullRequest->getFiles()
            ->filter(fn (File $file) => \in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED, File::STATUS_RENAMED], true))
            ->matches('tests/unit/**/*Test.php');

        $addedSrcFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('src/**/*.php');
        $missingUnitTests = [];
        $unitTestsName = [];

        // prepare phpunit code coverage exclude lists
        $excludedDirs = [];
        $excludedFiles = [];
        $dom = new \DOMDocument();

        $phpUnitConfigFromPullRequest = $context->platform->pullRequest->getFiles()
            ->matches('phpunit.xml.dist')
            ->first();

        $phpUnitConfigFromPullRequestContent = $phpUnitConfigFromPullRequest?->getContent();
        $phpUnitConfig = $phpUnitConfigFromPullRequest->name ?? $this->phpunitConfigPath;
        \assert($phpUnitConfig !== '');
        $domLoad = $phpUnitConfigFromPullRequestContent !== null && $phpUnitConfigFromPullRequestContent !== ''
            ? $dom->loadXML($phpUnitConfigFromPullRequestContent)
            : $dom->load($phpUnitConfig);

        if ($domLoad) {
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//source/exclude/directory') ?: [] as $dirDomElement) {
                $excludedDirs[] = [
                    'path' => rtrim((string) $dirDomElement->nodeValue, '/') . '/',
                    'suffix' => $dirDomElement instanceof \DOMElement ? $dirDomElement->getAttribute('suffix') : '',
                ];
            }

            foreach ($xpath->query('//source/exclude/file') ?: [] as $fileDomElements) {
                $excludedFiles[] = $fileDomElements->nodeValue;
            }
        } else {
            $context->warning(\sprintf('Was not able to load phpunit config file %s. Please check configuration.', $phpUnitConfig));
        }

        foreach ($addedUnitTests as $file) {
            $content = $file->getContent();

            preg_match('/\s+extends\s+(?<class>\w+)/', $content, $matches);

            if ((isset($matches['class']) && \in_array($matches['class'], self::BASE_TEST_CLASSES, true))
                || $this->hasVerifiedProjectTestBase($file, $context->platform->pullRequest)
            ) {
                $fqcn = str_replace('.php', '', $file->name);
                $className = explode('/', $fqcn);

                $unitTestsName[] = end($className);
            }
        }

        foreach ($addedSrcFiles as $file) {
            if ($this->isExcluded($file, $excludedDirs, $excludedFiles)) {
                continue;
            }

            $fqcn = str_replace('.php', '', $file->name);
            $className = explode('/', $fqcn);
            $class = end($className);

            if (!\in_array($class . 'Test', $unitTestsName, true)) {
                $missingUnitTests[] = $file->name;
            }
        }

        if ($missingUnitTests !== []) {
            $context->failure(
                'Please be kind and add unit tests for your new code in these files: <br/><br/>'
                . implode('<br/>', $missingUnitTests)
                . '<br/> You can run `composer make:coverage` to generate dummy unit tests for files that are not covered'
            );
        }
    }

    private function hasVerifiedProjectTestBase(File $file, PullRequest $pullRequest): bool
    {
        $class = 'Shopwell\\Tests\\Unit\\' . str_replace('/', '\\', substr($file->name, \strlen('tests/unit/'), -4));
        $parent = $this->classParent($file->getContent(), $class);
        $basePath = self::PROJECT_TEST_BASES[$parent ?? ''] ?? null;
        if ($parent === null || $basePath === null) {
            return false;
        }

        $base = $pullRequest->getFile($basePath);

        return $base->status !== File::STATUS_REMOVED
            && $this->classParent($base->getContent(), $parent) === 'PHPUnit\\Framework\\TestCase';
    }

    private function classParent(string $content, string $class): ?string
    {
        try {
            $tokens = array_values(array_filter(\PhpToken::tokenize($content, \TOKEN_PARSE), static fn (\PhpToken $token): bool => !$token->isIgnorable()));
        } catch (\ParseError) {
            return null;
        }

        $namespace = '';
        $imports = [];
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($token->text === '{') {
                ++$depth;
            } elseif ($token->text === '}') {
                --$depth;
            }
            if ($depth !== 0) {
                continue;
            }

            if ($token->id === \T_NAMESPACE) {
                if (($tokens[$index + 2]->text ?? '') !== ';') {
                    return null;
                }
                $namespace = $tokens[$index + 1]->text;
                $imports = [];
            }
            if ($token->id === \T_USE && isset($tokens[$index + 1])) {
                $name = $tokens[$index + 1]->text;
                $alias = substr($name, (int) strrpos($name, '\\') + 1);
                $end = $index + 2;
                if (($tokens[$end]->id ?? null) === \T_AS) {
                    $alias = $tokens[$end + 1]->text ?? '';
                    $end += 2;
                }
                if (($tokens[$end]->text ?? '') === ';') {
                    $imports[$alias] = ltrim($name, '\\');
                }
            }
            if ($token->id !== \T_CLASS
                || ($tokens[$index + 1]->id ?? null) !== \T_STRING
                || ltrim($namespace . '\\' . $tokens[$index + 1]->text, '\\') !== $class
                || ($tokens[$index + 2]->id ?? null) !== \T_EXTENDS
                || !isset($tokens[$index + 3])
            ) {
                continue;
            }

            $parent = $tokens[$index + 3]->text;
            if (str_starts_with($parent, '\\')) {
                return ltrim($parent, '\\');
            }
            $parts = explode('\\', $parent, 2);
            if (isset($imports[$parts[0]])) {
                return $imports[$parts[0]] . (isset($parts[1]) ? '\\' . $parts[1] : '');
            }

            return ltrim($namespace . '\\' . $parent, '\\');
        }

        return null;
    }

    /**
     * @param list<array{path: string, suffix: string}> $excludedDirs
     * @param list<string|null> $excludedFiles
     */
    private function isExcluded(File $file, array $excludedDirs, array $excludedFiles): bool
    {
        $content = $file->getContent();

        $fqcn = str_replace('.php', '', $file->name);
        $className = explode('/', $fqcn);
        $class = end($className);

        if (\str_contains($content, '* @codeCoverageIgnore')) {
            return true;
        }

        if (\str_contains($content, 'abstract class ' . $class)) {
            return true;
        }

        if (\str_contains($content, 'interface ' . $class)) {
            return true;
        }

        if (\str_contains($content, 'trait ' . $class)) {
            return true;
        }

        if (\str_starts_with($class, 'Migration1')) {
            return true;
        }

        // DependencyInjection service-wiring files (PHP closures using ContainerConfigurator) need no unit tests.
        if ((str_contains($file->name, '/DependencyInjection/') || preg_match('#/Resources/config/services(?:_[^/]*)?\.php$#D', $file->name) === 1) && str_contains($content, 'ContainerConfigurator')) {
            return true;
        }

        // process phpunit code coverage exclude lists
        if (\in_array($file->name, $excludedFiles, true)) {
            return true;
        }

        $dir = \dirname($file->name);
        $fileName = basename($file->name);

        foreach ($excludedDirs as $excludedDir) {
            if (str_starts_with($dir . '/', $excludedDir['path']) && str_ends_with($fileName, $excludedDir['suffix'])) {
                return true;
            }
        }

        foreach (self::IGNORE_SUFFIXES as $ignoreSuffix) {
            if (\str_ends_with($class, $ignoreSuffix)) {
                return true;
            }
        }

        return false;
    }
}
