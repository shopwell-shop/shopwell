<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

#[Package('framework')]
class ValueGeneratorPatternIncrement extends AbstractValueGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractIncrementStorage $incrementConnector)
    {
    }

    public function getPatternId(): string
    {
        return 'n';
    }

    /**
     * @param array<int, string> $args
     */
    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        if ($preview === true) {
            return (string) $this->incrementConnector->preview($config);
        }

        return (string) $this->incrementConnector->reserve($config);
    }

    public function getDecorated(): AbstractValueGenerator
    {
        throw new DecorationPatternException(self::class);
    }
}
