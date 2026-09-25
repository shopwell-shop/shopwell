<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Indexing;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class SynchronousPostUpdateIndexer extends PostUpdateIndexer
{
}
