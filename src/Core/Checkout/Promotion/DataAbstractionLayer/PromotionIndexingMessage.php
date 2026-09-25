<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionIndexingMessage extends EntityIndexingMessage
{
}
