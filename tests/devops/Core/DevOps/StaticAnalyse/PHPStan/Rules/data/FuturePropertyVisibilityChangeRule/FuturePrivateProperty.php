<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyVisibilityChangeRule;

use Shopwell\Core\Framework\Deprecation\BCChange\VisibilityChange;

class FuturePrivateProperty
{
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'private')]
    public static string $value = 'value';
}
