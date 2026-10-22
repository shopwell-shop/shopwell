<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationDefinition;
use Shopwell\Core\System\Organization\OrganizationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'organization_unit';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrganizationUnitCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrganizationUnitEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrganizationDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the organization unit.'),
            (new FkField('organization_id', 'organizationId', OrganizationDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the organization this unit belongs to.'),
            (new ParentFkField(self::class))->addFlags(new ApiAware())->setDescription('Unique identity of the parent organization unit.'),
            (new TreeLevelField('level', 'level'))->addFlags(new ApiAware())->setDescription('An integer value that denotes the level of nesting of the organization unit inside the unit tree.'),
            (new TreePathField('path', 'path'))->addFlags(new ApiAware())->setDescription('The ids of all parent organization units, concatenated as path.'),
            (new ChildCountField())->addFlags(new ApiAware())->setDescription('Number of direct child organization units, maintained by the indexer.'),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of the organization unit.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the organization unit is available for selection.'),

            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware())->setDescription('Parent organization unit.'),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware())->setDescription('Child organization units within this unit.'),
            new ManyToOneAssociationField('organization', 'organization_id', OrganizationDefinition::class, 'id', false),
            (new TranslationsAssociationField(OrganizationUnitTranslationDefinition::class, 'organization_unit_id'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
