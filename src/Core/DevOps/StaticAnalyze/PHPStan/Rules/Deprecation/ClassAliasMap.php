<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use Shopwell\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ClassAliasMap
{
    /**
     * @var array<lowercase-string, class-string>
     */
    private array $classAliases = [];

    /**
     * @var array<lowercase-string, list<non-empty-string>>
     */
    private array $aliasesByCanonicalClassName = [];

    /**
     * @param array<non-empty-string, class-string>|null $classAliases
     */
    public function __construct(?array $classAliases = null)
    {
        $classAliases ??= ClassAliasRegistry::aliases();

        foreach ($classAliases as $previousClassName => $currentClassName) {
            $this->classAliases[\strtolower($previousClassName)] = $currentClassName;
            $this->aliasesByCanonicalClassName[\strtolower($currentClassName)][] = $previousClassName;
        }
    }

    /**
     * @return class-string|null
     */
    public function canonicalClassName(string $className): ?string
    {
        return $this->classAliases[\strtolower($className)] ?? null;
    }

    /**
     * @return list<non-empty-string>
     */
    public function aliasesForCanonicalClassName(string $className): array
    {
        return $this->aliasesByCanonicalClassName[\strtolower($className)] ?? [];
    }
}
