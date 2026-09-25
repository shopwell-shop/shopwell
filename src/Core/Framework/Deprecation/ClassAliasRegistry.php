<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Deprecation;

use Shopwell\Core\Framework\Adapter\Asset\AssetService;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\Api\NotificationController;
use Shopwell\Core\Framework\Notification\NotificationCollection;
use Shopwell\Core\Framework\Notification\NotificationDefinition;
use Shopwell\Core\Framework\Notification\NotificationEntity;

/**
 * @internal
 */
#[Package('framework')]
final class ClassAliasRegistry
{
    /**
     * The keys cannot be class-string because the legacy classes intentionally have no declarations.
     *
     * @var array<non-empty-string, class-string>
     */
    public const ALIASES = [
        'Shopwell\Core\Framework\Plugin\Util\AssetService' => AssetService::class,
        'Shopwell\Administration\Controller\NotificationController' => NotificationController::class,
        'Shopwell\Administration\Notification\NotificationCollection' => NotificationCollection::class,
        'Shopwell\Administration\Notification\NotificationDefinition' => NotificationDefinition::class,
        'Shopwell\Administration\Notification\NotificationEntity' => NotificationEntity::class,
        'Shopwell\Elasticsearch\Product\SearchConfigLoader' => SearchConfigLoader::class,
    ];

    /**
     * @var array<non-empty-string, class-string>
     */
    private static array $packageAliases = [];

    /**
     * Registers aliases contributed by an installed extension from its Composer autoload file.
     *
     * @param array<non-empty-string, class-string> $aliases
     */
    public static function registerAliases(array $aliases): void
    {
        foreach ($aliases as $previousClassName => $currentClassName) {
            $registeredClassName = self::canonicalClassName($previousClassName);
            if ($registeredClassName !== null && $registeredClassName !== $currentClassName) {
                // @phpstan-ignore shopwell.domainException (Composer bootstrap validates conflicting alias declarations.)
                throw new \LogicException(\sprintf(
                    'Cannot register class alias "%s" to "%s": the alias is already registered for "%s".',
                    $previousClassName,
                    $currentClassName,
                    $registeredClassName
                ));
            }

            if ($registeredClassName === null) {
                self::$packageAliases[$previousClassName] = $currentClassName;
            }

            self::registerAlias($previousClassName, $currentClassName);
        }
    }

    /**
     * @return array<non-empty-string, class-string>
     */
    public static function aliases(): array
    {
        return self::ALIASES + self::$packageAliases;
    }

    private static function canonicalClassName(string $previousClassName): ?string
    {
        foreach (self::aliases() as $registeredPreviousClassName => $registeredClassName) {
            if (strtolower($registeredPreviousClassName) === strtolower($previousClassName)) {
                return $registeredClassName;
            }
        }

        return null;
    }

    /**
     * @param non-empty-string $previousClassName
     * @param class-string $currentClassName
     */
    private static function registerAlias(string $previousClassName, string $currentClassName): void
    {
        if (class_exists($previousClassName, autoload: false)) {
            $registeredClassName = (new \ReflectionClass($previousClassName))->getName();

            if ($registeredClassName !== $currentClassName) {
                // @phpstan-ignore shopwell.domainException (Composer bootstrap validates conflicting alias declarations.)
                throw new \LogicException(\sprintf('Cannot register class alias "%s" to "%s": the name already refers to "%s".', $previousClassName, $currentClassName, $registeredClassName));
            }

            return;
        }

        if (!class_exists($currentClassName)) {
            // @phpstan-ignore shopwell.domainException (Composer bootstrap validates contributed aliases.)
            throw new \LogicException(\sprintf('Cannot register class alias "%s" to "%s": the canonical class does not exist.', $previousClassName, $currentClassName));
        }

        class_alias($currentClassName, $previousClassName);
    }

    /**
     * PhpStorm only recognizes explicit class_alias() calls. Keep this method in sync for Core IDE support;
     * ClassAliasRegistry::aliases() remains the authoritative list and this method is never called.
     *
     * @codeCoverageIgnore
     */
    // @phpstan-ignore method.unused (PhpStorm indexes these declarations without calling the method.)
    private static function declareAliasesForIde(): void
    {
        class_alias(AssetService::class, 'Shopwell\Core\Framework\Plugin\Util\AssetService');
        class_alias(NotificationController::class, 'Shopwell\Administration\Controller\NotificationController');
        class_alias(NotificationCollection::class, 'Shopwell\Administration\Notification\NotificationCollection');
        class_alias(NotificationDefinition::class, 'Shopwell\Administration\Notification\NotificationDefinition');
        class_alias(NotificationEntity::class, 'Shopwell\Administration\Notification\NotificationEntity');
        class_alias(SearchConfigLoader::class, 'Shopwell\Elasticsearch\Product\SearchConfigLoader');
    }
}
