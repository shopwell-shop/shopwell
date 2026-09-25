<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
interface RequestContextResolverInterface
{
    public function resolve(Request $request): void;
}
