<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversAttributeRule\Unit;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Struct\CloneTrait;

#[CoversTrait(CloneTrait::class)]
class CoversTraitFixture extends TestCase
{
}
