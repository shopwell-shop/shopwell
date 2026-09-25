<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution;

use Shopwell\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Util\Hasher;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptEnvironmentFactory implements ResetInterface
{
    private const CACHE_LIMIT = 250;

    /**
     * @var array<string, TwigEnvironment>
     */
    private array $twigEnvs = [];

    /**
     * @param iterable<ExtensionInterface> $twigExtensions
     *
     * @internal
     */
    public function __construct(
        private readonly DebugExtension $debugExtension,
        private readonly iterable $twigExtensions,
        private readonly string $shopwellVersion,
    ) {
    }

    public function initEnv(Script $script): TwigEnvironment
    {
        $scriptHash = Hasher::hash($script->getName() . $script->getScript() . serialize($script->getTwigOptions()));

        if (isset($this->twigEnvs[$scriptHash])) {
            return $this->twigEnvs[$scriptHash];
        }

        $twig = new TwigEnvironment(
            new ScriptTwigLoader($script),
            $script->getTwigOptions()
        );

        foreach ($this->twigExtensions as $twigExtension) {
            $twig->addExtension($twigExtension);
        }

        if ($script->getTwigOptions()['debug'] ?? false) {
            $twig->addExtension($this->debugExtension);
        }

        $twig->addGlobal('shopwell', new ArrayStruct([
            'version' => $this->shopwellVersion,
        ]));

        // memoize 250 envs at max, to prevent memory leaks
        if (\count($this->twigEnvs) < self::CACHE_LIMIT) {
            $this->twigEnvs[$scriptHash] = $twig;
        }

        return $twig;
    }

    public function reset(): void
    {
        $this->twigEnvs = [];
    }
}
