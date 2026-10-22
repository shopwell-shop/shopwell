<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DependencyInjection;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the payment interfaces the installation declares, at container compile time.
 *
 * This pass is what makes "a plugin adds a payment interface" a matter of one service declaration. It
 * groups everything tagged `shopwell.payment.provider` by the technical name in the tag's `key`, orders
 * each group by `priority` and answers the three questions that can only be answered before the
 * container exists:
 *
 *  1. **Is the technical name addressable?** It becomes a path segment of the notify url, so it has to
 *     match `[A-Za-z0-9_-]+` (`Api/NotifyController`). A name that fails here would produce an
 *     interface whose callbacks can never arrive - a failure that is otherwise only noticed when a
 *     payment stays unpaid.
 *  2. **Is a second implementation of the same name a decorator?** A technical name is a foreign key
 *     value in `payment_order`, `payment_provider_config` and `payment_app_provider`, so a second
 *     implementation that simply took over would leave every historic row uninterpretable. Extending
 *     an interface is done through `getDecorated()`, replacing it by using a technical name of its own.
 *  3. **Which instance represents the interface?** The highest priority one, because that is the
 *     outermost decorator and therefore the one that has seen every other declaration in the chain.
 *  4. **Can the declarations of one interface be ordered at all?** Two of them with the same priority
 *     leave the order to the registration order of the container, and the outermost decorator would
 *     change whenever an unrelated plugin installs itself.
 *
 * Everything that needs the object itself - the credential and channel checks, the capability
 * coherence, the comparison of the tag key with the name the declaration gives itself - is done by
 * {@see PaymentProviderFactory} when the per interface definition is built, which happens lazily on
 * the first use of the registry.
 *
 * A container without a single tagged service is not an error: the registry simply stays empty, which
 * is what an installation that ships no payment interface at all looks like.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderCompilerPass implements CompilerPassInterface
{
    /**
     * The tag a declaration of a payment interface carries. Attributes: `key` (the technical name,
     * required) and `priority` (default 0, lower means "closer to the base implementation").
     */
    final public const PROVIDER_TAG = 'shopwell.payment.provider';

    /**
     * The decoration chain per interface, as an ordered list of declaration class names, outermost
     * first. Read by `payment:providers:validate` to print "who decorates whom".
     */
    final public const DECORATION_CHAIN_PARAMETER = 'shopwell.payment.provider.decoration_chain';

    /**
     * The prefix of the per interface service id, e.g. `shopwell.payment.provider.alipay`.
     */
    private const DEFINITION_PREFIX = 'shopwell.payment.provider.';

    /**
     * The character set the notify url route accepts for the provider segment.
     */
    private const NAME_PATTERN = '/^[A-Za-z0-9_-]+$/';

    public function process(ContainerBuilder $container): void
    {
        $grouped = $this->declarationsByKey($container);

        ksort($grouped);

        $references = [];
        $chain = [];

        foreach ($grouped as $key => $declarations) {
            $ordered = $this->orderedByPriority($key, $declarations);
            $base = $ordered[\count($ordered) - 1];

            $this->assertDecorators($key, $ordered, $base);

            $definition = new Definition(PaymentProvider::class);
            $definition->setFactory([new Reference(PaymentProviderFactory::class), 'create']);
            // the key travels with the reference: the factory compares it with the name the
            // declaration gives itself, which is the only place the two can be compared at all
            $definition->setArguments([new Reference($ordered[0]['id']), $key]);
            $definition->setPublic(false);

            $container->setDefinition(self::DEFINITION_PREFIX . $key, $definition);

            $references[] = new Reference(self::DEFINITION_PREFIX . $key);
            $chain[$key] = array_map(
                static fn (array $declaration): string => $declaration['class'],
                $ordered
            );
        }

        $container->setParameter(self::DECORATION_CHAIN_PARAMETER, $chain);

        if (!$container->hasDefinition(PaymentProviderRegistry::class)) {
            return;
        }

        // an empty iterator is a valid answer: an installation may ship no interface at all
        $container->getDefinition(PaymentProviderRegistry::class)
            ->setArgument(0, new IteratorArgument($references));
    }

    /**
     * @return array<string, list<array{id: string, class: string, priority: int}>>
     */
    private function declarationsByKey(ContainerBuilder $container): array
    {
        $grouped = [];

        foreach ($container->findTaggedServiceIds(self::PROVIDER_TAG) as $id => $tags) {
            $id = (string) $id;
            $class = $this->classOf($container, $id);

            foreach ($tags as $attributes) {
                $key = $attributes['key'] ?? null;

                if (!\is_string($key) || $key === '') {
                    throw PaymentException::invalidProviderName($id);
                }

                if (preg_match(self::NAME_PATTERN, $key) !== 1) {
                    throw PaymentException::invalidProviderName($key);
                }

                $priority = $attributes['priority'] ?? 0;

                $grouped[$key][] = [
                    'id' => $id,
                    'class' => $class,
                    'priority' => \is_int($priority) ? $priority : (int) $priority,
                ];
            }
        }

        return $grouped;
    }

    /**
     * The class that is actually instantiated, and a check that it is a declaration at all.
     *
     * @throws PaymentException
     */
    private function classOf(ContainerBuilder $container, string $id): string
    {
        if (!$container->hasDefinition($id)) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The service "%s" is tagged as a payment provider, but it is not a definition this container can build.',
                $id
            ));
        }

        $definition = $container->getDefinition($id);
        $class = $definition->getClass() ?? $id;

        $reflection = $container->getReflectionClass($class, false);

        if ($reflection === null || !$reflection->isSubclassOf(AbstractPaymentProvider::class)) {
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The service "%s" is tagged as a payment provider, but "%s" does not extend %s.',
                $id,
                $class,
                AbstractPaymentProvider::class
            ));
        }

        /** @var class-string<AbstractPaymentProvider> $className */
        $className = $reflection->getName();

        return $className;
    }

    /**
     * Highest priority first, because the highest priority declaration is the outermost decorator.
     *
     * Two declarations of one interface with the same priority cannot be ordered by their priority, so
     * the container would decide the order by registration - which changes whenever an unrelated
     * plugin installs itself. The chain only has one valid order, so the tie is refused instead of
     * being resolved by chance.
     *
     * @param list<array{id: string, class: string, priority: int}> $declarations
     *
     * @throws PaymentException
     *
     * @return list<array{id: string, class: string, priority: int}>
     */
    private function orderedByPriority(string $key, array $declarations): array
    {
        $seen = [];

        foreach ($declarations as $declaration) {
            $priority = $declaration['priority'];

            if (isset($seen[$priority])) {
                throw PaymentException::ambiguousProviderPriority(
                    $key,
                    $priority,
                    $seen[$priority]['class'],
                    $declaration['class']
                );
            }

            $seen[$priority] = $declaration;
        }

        usort(
            $declarations,
            static fn (array $left, array $right): int => $right['priority'] <=> $left['priority']
        );

        return $declarations;
    }

    /**
     * Every declaration but the base one has to be a decorator.
     *
     * "Is a decorator" is answered on the class, not on the instance: the class has to override
     * `getDecorated()`, and overriding it is the only supported way to say "I wrap the previous
     * implementation". A class that merely happens to be registered twice is the conflict this pass
     * exists for.
     *
     * @param list<array{id: string, class: string, priority: int}> $ordered
     * @param array{id: string, class: string, priority: int} $base
     *
     * @throws PaymentException
     */
    private function assertDecorators(string $key, array $ordered, array $base): void
    {
        $baseDeclaringClass = $this->declaringClassOfGetDecorated($base['class']);

        for ($i = 0, $count = \count($ordered) - 1; $i < $count; ++$i) {
            $declaringClass = $this->declaringClassOfGetDecorated($ordered[$i]['class']);

            if ($declaringClass !== null && $declaringClass !== AbstractPaymentProvider::class) {
                continue;
            }

            throw PaymentException::duplicateProvider($key, $base['class'], $ordered[$i]['class']);
        }

        if ($baseDeclaringClass !== null && $baseDeclaringClass !== AbstractPaymentProvider::class) {
            // the base implementation overrides getDecorated() as well, so there is no end of the chain
            throw PaymentException::invalidProviderDeclaration(\sprintf(
                'The payment interface "%s" is declared by "%s", which overrides getDecorated() without decorating anything - the chain has no base implementation.',
                $key,
                $base['class']
            ));
        }
    }

    /**
     * The class that declares `getDecorated()` for this declaration, or null when nothing declares it.
     *
     * The answer `AbstractPaymentProvider` means "the inherited default that throws", i.e. a base
     * implementation.
     */
    private function declaringClassOfGetDecorated(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->hasMethod('getDecorated')) {
            return null;
        }

        $declaringClass = $reflection->getMethod('getDecorated')->getDeclaringClass()->getName();

        return $declaringClass;
    }
}
