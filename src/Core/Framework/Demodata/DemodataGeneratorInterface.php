<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Demodata;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface DemodataGeneratorInterface
{
    public function getDefinition(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void;
}
