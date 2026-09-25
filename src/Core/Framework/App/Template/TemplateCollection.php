<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Template;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<TemplateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class TemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TemplateEntity::class;
    }
}
