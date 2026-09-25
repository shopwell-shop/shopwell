<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule;

use Shopwell\Core\Framework\Deprecation\BCChange\ClassMoved;

#[ClassMoved(version: 'v6.8.0', previousClassName: 'Shopwell\Tests\Legacy\UnregisteredClass')]
class ClassMovedAttributeUsage
{
}
