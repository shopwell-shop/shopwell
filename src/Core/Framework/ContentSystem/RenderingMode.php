<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem;

use Shopwell\Core\Framework\Log\Package;

/**
 * Controls content rendering pipeline behavior.
 *
 * FULL: Complete pipeline - preparation, render, finalization.
 * SKELETON: Skip hydration - returns layout structure without loaded data.
 */
#[Package('framework')]
enum RenderingMode: string
{
    case FULL = 'full';
    case SKELETON = 'skeleton';
}
