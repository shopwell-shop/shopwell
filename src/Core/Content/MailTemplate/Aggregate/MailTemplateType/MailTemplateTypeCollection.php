<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateTypeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailTemplateTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateTypeEntity::class;
    }
}
