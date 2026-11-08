<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\BCChangeMarkers;
use Shopwell\Core\Framework\Bundle;
use Shopwell\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\Demodata\Command\DemodataCommand;
use Shopwell\Core\Framework\Demodata\DemodataContext;
use Shopwell\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopwell\Core\Framework\Demodata\DemodataRequest;
use Shopwell\Core\Framework\Demodata\DemodataService;
use Shopwell\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class InternalClassRule implements Rule
{
    private const INTERNAL_NAMESPACES = [
        '\\DevOps\\StaticAnalyze',
        '\\Core\\Maintenance',
        '\\ContentSystem\\',
    ];
    private const SUBSCRIBER_EXCEPTIONS = [
        RefreshIndexCommand::class,
    ];
    private const MESSAGE_HANDLER_EXCEPTIONS = [
        EntityIndexerRegistry::class,
    ];
    private const DEMO_DATA_EXCEPTIONS = [
        DemodataContext::class,
        DemodataGeneratorInterface::class,
        DemodataRequest::class,
        DemodataService::class,
        DemodataCommand::class,
        DemodataRequestCreatedEvent::class,
    ];
    /**
     * The content-system module's public extension surface: every class a plugin must reach to use it.
     * These carry no `@internal`; every other class in the module still must. Held as plain strings so
     * this rule takes on no dependency on the module's namespace.
     *
     * The test is whether a plugin imports the class, not whether its data reaches a client. A response
     * body's shape is a wire contract and needs no PHP one beside it, so the classes the encoders serialize
     * stay internal; a class named in a signature a plugin can reach does not.
     */
    private const CONTENT_SYSTEM_PUBLIC_SURFACE = [
        'Shopwell\\Core\\Framework\\ContentSystem\\Event\\ContentTreePreparationEvent',
        'Shopwell\\Core\\Framework\\ContentSystem\\Event\\RenderedTreeFinalizationEvent',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\AbstractContentDataLoader',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\AbstractContentDataLoaderConfigSerializer',
        'Shopwell\\Core\\Framework\\ContentSystem\\Mapping\\Provider\\AbstractMappingCandidateProvider',
        'Shopwell\\Core\\Framework\\ContentSystem\\Mapping\\MappingSourceReference',
        'Shopwell\\Core\\Framework\\ContentSystem\\Mapping\\MappingSourceResolution',
        'Shopwell\\Core\\Framework\\ContentSystem\\Mapping\\MappingSourceResolutionContext',
        'Shopwell\\Core\\Framework\\ContentSystem\\Output\\Format\\AbstractResponseFactory',
        'Shopwell\\Core\\Framework\\ContentSystem\\Adapter\\AbstractSpecificationSource',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Type\\Loader\\AbstractContentSystemElementTypeLoader',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\Loader\\AbstractContentSystemStyleOptionLoader',
        'Shopwell\\Core\\Framework\\ContentSystem\\Binding\\Loader\\AbstractContentSystemBindingSpecificationLoader',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\StoredElement',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\StoredValue',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\StoredTree',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ContextDefinitions',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ContextProvider',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ContextConsumer',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ConsumerScope',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataContext\\ContextType',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\Distribution\\DistributionConfig',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\Distribution\\DistributionStrategy',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\DataRequirement\\DataRequirement',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\ElementStyle',
        'Shopwell\\Core\\Framework\\ContentSystem\\Rendering\\RenderedElement',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\RenderedTreeEditor',
        'Shopwell\\Core\\Framework\\ContentSystem\\Cache\\RenderingCacheContext',
        'Shopwell\\Core\\Framework\\ContentSystem\\LayoutReference',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Entity\\ContentLayoutEntity',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Entity\\ContentLayoutCollection',
        'Shopwell\\Core\\Framework\\ContentSystem\\Output\\Struct\\ContentPage',
        'Shopwell\\Core\\Framework\\ContentSystem\\Adapter\\Entity\\AbstractContentLayoutAssignmentEntity',
        'Shopwell\\Storefront\\ContentSystem\\HeaderContentLayout\\HeaderContentLayoutEntity',
        'Shopwell\\Storefront\\ContentSystem\\HeaderContentLayout\\HeaderContentLayoutCollection',
        'Shopwell\\Storefront\\ContentSystem\\FooterContentLayout\\FooterContentLayoutEntity',
        'Shopwell\\Storefront\\ContentSystem\\FooterContentLayout\\FooterContentLayoutCollection',
        'Shopwell\\Core\\Framework\\ContentSystem\\RenderableLayout',
        'Shopwell\\Core\\Framework\\ContentSystem\\ResolvedContentLayout',
        'Shopwell\\Core\\Framework\\ContentSystem\\ContentSection',
        'Shopwell\\Core\\Framework\\ContentSystem\\RenderingSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\PlaceholderValues',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\AbstractContentDataLoaderConfig',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\LoaderInputs',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\ContentDataLoaderResult',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\LoaderConfigSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\ConfigKeySpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\ConfigKeyKind',
        'Shopwell\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\LoaderTypeCapability',
        'Shopwell\\Core\\Framework\\ContentSystem\\Output\\RenderResult',
        'Shopwell\\Core\\Framework\\ContentSystem\\Output\\Index\\ResolvedValueIndex',
        'Shopwell\\Core\\Framework\\ContentSystem\\RenderingMode',
        'Shopwell\\Core\\Framework\\ContentSystem\\SalesChannel\\AbstractContentRouteResponse',
        'Shopwell\\Core\\Framework\\ContentSystem\\Resolution\\ProvidedContext',
        'Shopwell\\Core\\Framework\\ContentSystem\\SpecificationData',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\ContentSystemElementTypeSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\PropertySpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\PropertyType',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\SlotSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\CopilotSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\Specification\\StyleOptionSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\Specification\\StyleOptionValueType',
        'Shopwell\\Core\\Framework\\ContentSystem\\Binding\\Specification\\BindingSpecification',
        'Shopwell\\Core\\Framework\\ContentSystem\\Binding\\Specification\\LoaderBinding',
        'Shopwell\\Core\\Framework\\ContentSystem\\Binding\\Specification\\BindingInput',
        'Shopwell\\Core\\Framework\\ContentSystem\\Mapping\\MappingCandidate',
        'Shopwell\\Core\\Framework\\ContentSystem\\Mapping\\Registry\\AbstractContentSystemMappingCandidateRegistry',
        'Shopwell\\Core\\Framework\\ContentSystem\\ContentSystemException',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $doc = $node->getDocComment()?->getText() ?? '';

        if ($this->isInternal($doc, $node->getClassReflection())) {
            return [];
        }

        $class = $node->getClassReflection()->getName();

        if ($this->isExample($node)) {
            return [];
        }
        if ($this->isTestClass($node)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Test classes (%s) must be flagged @internal to not be captured by the BC checker',
                    $node->getClassReflection()->getName()
                ))
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isStorefrontController($node)) {
            return [
                RuleErrorBuilder::message('Storefront controllers must be flagged @internal to not be captured by the BC checker. The BC promise is checked over the route annotation.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isBundle($node)) {
            return [
                RuleErrorBuilder::message('Bundles must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($node->getClassReflection()->implementsInterface(CompilerPassInterface::class)) {
            return [
                RuleErrorBuilder::message('Compiler passes must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isEventSubscriber($node) && !$this->isFinal($node->getClassReflection(), $doc) && !\in_array($class, self::SUBSCRIBER_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('Event subscribers must be flagged @internal or @final to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if (($namespace = $this->isInInternalNamespace($node)) && !\in_array($class, self::CONTENT_SYSTEM_PUBLIC_SURFACE, true)) {
            return [
                RuleErrorBuilder::message('Classes in `' . $namespace . '` namespace must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isInNamespace($node, '\\Framework\\Demodata') && !\in_array($class, self::DEMO_DATA_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('Classes in `Framework\\Demodata` namespace must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isMigrationStep($node)) {
            return [
                RuleErrorBuilder::message('Migrations must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isMessageHandler($node) && !\in_array($class, self::MESSAGE_HANDLER_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('MessageHandlers must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        if ($this->isParentInternalAndAbstract($scope) && !$this->isFinal($node->getClassReflection(), $doc)) {
            return [
                RuleErrorBuilder::message('Classes that extend an @internal abstract class must be flagged @internal or @final to not be captured by the BC checker.')
                    ->identifier('shopwell.internalClass')
                    ->build(),
            ];
        }

        return [];
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, 'Shopwell\\Core\\Test\\Stub\\')) {
            return false;
        }

        if (\str_contains($namespace, '\\Test\\Integration\\Builder\\')) {
            // Test builder classes are not internal by design
            return false;
        }

        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if (\str_contains($namespace, '\\Tests\\')) {
            return true;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }

    private function isInternal(string $doc, ClassReflection $class): bool
    {
        return \str_contains($doc, '@internal')
            || BCChangeMarkers::has(BecomesInternal::class, $class);
    }

    private function isStorefrontController(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        /** @phpstan-ignore phpat.restrictNamespacesInCore (only class constant is used) */
        return $class->getParentClass()->getName() === StorefrontController::class;
    }

    private function isBundle(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        if ($class->isAnonymous()) {
            return false;
        }

        return $class->getParentClass()->getName() === Bundle::class && $class->getName() !== Plugin::class;
    }

    private function isEventSubscriber(InClassNode $node): bool
    {
        foreach ($node->getClassReflection()->getInterfaces() as $interface) {
            if ($interface->getName() === EventSubscriberInterface::class) {
                return true;
            }
        }

        return false;
    }

    private function isInInternalNamespace(InClassNode $node): ?string
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, 'Shopwell\\Core\\Test\\Stub\\')) {
            // Test stub classes are public builders by design, as isTestClass() already encodes.
            // This exempts them from the namespace requirement only; a stub extending an
            // @internal abstract still has to be @internal or @final.
            return null;
        }

        foreach (self::INTERNAL_NAMESPACES as $internalNamespace) {
            if (\str_contains($namespace, $internalNamespace)) {
                return $internalNamespace;
            }
        }

        return null;
    }

    private function isInNamespace(InClassNode $node, string $namespace): bool
    {
        return \str_contains($node->getClassReflection()->getName(), $namespace);
    }

    private function isMigrationStep(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        return $class->getParentClass()->getName() === MigrationStep::class;
    }

    private function isMessageHandler(InClassNode $node): bool
    {
        $class = $node->getClassReflection()->getNativeReflection();

        if ($class->isAbstract()) {
            // abstract base classes should not be final
            return false;
        }

        return $class->getAttributes(AsMessageHandler::class) !== [];
    }

    private function isFinal(ClassReflection $class, string $doc): bool
    {
        return str_contains($doc, '@final')
            || $class->isFinal()
            || BCChangeMarkers::has(BecomesFinal::class, $class);
    }

    private function isParentInternalAndAbstract(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        \assert($class !== null);
        $parent = $class->getParentClass();

        if ($parent === null) {
            return false;
        }

        if (!$parent->isAbstract()) {
            return false;
        }

        $native = $parent->getNativeReflection();

        $doc = $native->getDocComment() ?: '';

        return $this->isInternal($doc, $parent);
    }

    private function isExample(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        return \str_contains($namespace, 'Shopwell\\Tests\\Examples\\');
    }
}
