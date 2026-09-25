<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Contract;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface IdAware
{
    public function getId(): string;
}
