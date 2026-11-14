<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Entity;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowPublicationTest
 */
#[Package('framework')]
class WorkflowReleaseDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'workflow_release';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WorkflowReleaseEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WorkflowReleaseCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        // Public APIs cannot mutate or delete snapshots; publication owns insertion.
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class)),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware(AdminApiSource::class), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('revision', 'revision'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new IdField('draft_id', 'draftId'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new StringField('publisher', 'publisher'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new IdField('published_by', 'publishedBy'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            // A validated JSON string preserves numeric object keys without sending objects through DAL JSON validation.
            (new LongTextField('document', 'document'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
        ]);
    }
}
