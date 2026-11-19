<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
enum ApprovalPolicy: string
{
    case Single = ApprovalNodeConfiguration::SINGLE;
    case All = ApprovalNodeConfiguration::ALL;
    case Any = ApprovalNodeConfiguration::ANY;
}
