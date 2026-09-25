<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopwell\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopwell\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyException;
use Shopwell\Core\Framework\Adapter\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Shopwell\Core\Framework\Framework;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationException;
use Shopwell\Core\Kernel;
use Shopwell\Core\Migration\Traits\StateMachineMigrationImporter;
use Shopwell\Core\Migration\V6_4\Migration1632721037OrderDocumentMailTemplate;
use Shopwell\Core\Migration\V6_5\Migration1672931011ReviewFormMailTemplate;
use Symfony\Component\Console\Command\Command;

/**
 * @internal
 *
 * @implements Rule<Throw_>
 */
#[Package('framework')]
class DomainExceptionRule implements Rule
{
    use InTestClassTrait;

    /**
     * @var list<string>
     */
    private const VALID_SUB_DOMAINS = [
        'Cart',
        'Payment',
        'Order',
    ];

    /**
     * @var list<string>
     */
    private const EXCLUDED_NAMESPACES = [
        'Shopwell\Core\DevOps\StaticAnalyze\\',
    ];

    /**
     * @var array<string, string>
     */
    private const REMAPPED_DOMAINS = [
        Kernel::class => FrameworkException::class,
        Framework::class => FrameworkException::class,
        VarnishReverseProxyGateway::class => ReverseProxyException::class,
        FastlyReverseProxyGateway::class => ReverseProxyException::class,
        Migration1672931011ReviewFormMailTemplate::class => MigrationException::class,
        Migration1632721037OrderDocumentMailTemplate::class => MigrationException::class,
        StateMachineMigrationImporter::class => MigrationException::class,
    ];

    /**
     * @var array<string>
     */
    private array $validExceptionClasses;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly Configuration $configuration,
    ) {
        // see src/Core/DevOps/StaticAnalyze/PHPStan/common.neon for the default config
        $this->validExceptionClasses = $this->configuration->getAllowedNonDomainExceptions();
    }

    public function getNodeType(): string
    {
        return Throw_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
            return [];
        }

        if (!$node instanceof Throw_) {
            return [];
        }

        if ($node->expr instanceof StaticCall) {
            return $this->validateDomainExceptionClass($node->expr, $scope);
        }

        if (!$node->expr instanceof New_) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if (\is_string($namespace)) {
            foreach (self::EXCLUDED_NAMESPACES as $excludedNamespace) {
                if (\str_starts_with($namespace, $excludedNamespace)) {
                    return [];
                }
            }
        }

        \assert($node->expr->class instanceof Name);
        $exceptionClass = $node->expr->class->toString();

        if (\in_array($exceptionClass, $this->validExceptionClasses, true)) {
            return [];
        }

        // Allow InvalidArgumentException in commands to validate user input
        if ($scope->getClassReflection()->is(Command::class) && $exceptionClass === 'InvalidArgumentException') {
            return [];
        }

        return [
            RuleErrorBuilder::message('Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/shopware/platform/blob/v6.4.20.0/adr/2022-02-24-domain-exceptions.md')
                ->identifier('shopware.domainException')
                ->build(),
        ];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateDomainExceptionClass(StaticCall $node, Scope $scope): array
    {
        \assert($node->class instanceof Name);
        $exceptionClass = $node->class->toString();

        if (!\str_starts_with($exceptionClass, 'Shopwell\\Core\\')) {
            return [];
        }

        $exception = $this->reflectionProvider->getClass($exceptionClass);
        if (!$exception->is(HttpException::class)) {
            return [
                RuleErrorBuilder::message(\sprintf('Domain exception class %s has to extend the \Shopwell\Core\Framework\HttpException class', $exceptionClass))
                    ->identifier('shopware.domainException')
                    ->build(),
            ];
        }

        $reflection = $scope->getClassReflection();
        \assert($reflection !== null);
        if (!\str_starts_with($reflection->getName(), 'Shopwell\\Core\\')) {
            return [];
        }

        if ($this->isRemapped($reflection->getName(), $exceptionClass)) {
            return [];
        }

        $parts = \explode('\\', $reflection->getName());

        $domain = $parts[2] ?? '';
        $sub = $parts[3] ?? '';

        $acceptedClasses = [
            \sprintf('Shopwell\\Core\\%s\\%s\\%sException', $domain, $sub, $sub),
            \sprintf('Shopwell\\Core\\%s\\%sException', $domain, $domain),
        ];

        foreach ($acceptedClasses as $expected) {
            if ($exceptionClass === $expected || $exception->is($expected)) {
                return [];
            }
        }

        // Is it in a subdomain?
        if (isset($parts[5]) && \in_array($parts[4], self::VALID_SUB_DOMAINS, true)) {
            $expectedSub = \sprintf('\\%s\\%sException', $parts[4], $parts[4]);
            if (\str_starts_with(strrev($exceptionClass), strrev($expectedSub))) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf('Expected domain exception class %s, got %s', $acceptedClasses[0], $exceptionClass))
                ->identifier('shopware.domainException')
                ->build(),
        ];
    }

    private function isRemapped(string $source, string $exceptionClass): bool
    {
        if (!\array_key_exists($source, self::REMAPPED_DOMAINS)) {
            return false;
        }

        return self::REMAPPED_DOMAINS[$source] === $exceptionClass;
    }
}
