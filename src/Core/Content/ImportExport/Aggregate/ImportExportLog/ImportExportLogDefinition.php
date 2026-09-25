<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Shopwell\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ImportExportLogEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('activity', 'activity'))->addFlags(new Required()),
            (new StringField('state', 'state'))->addFlags(new Required()),
            (new IntField('records', 'records'))->addFlags(new Required()),
            new FkField('user_id', 'userId', UserDefinition::class),
            new FkField('profile_id', 'profileId', ImportExportProfileDefinition::class),
            new FkField('file_id', 'fileId', ImportExportFileDefinition::class),
            new FkField('invalid_records_log_id', 'invalidRecordsLogId', ImportExportLogDefinition::class),
            new StringField('username', 'username'),
            new StringField('profile_name', 'profileName'),
            (new JsonField('config', 'config', [], []))->addFlags(new Required()),
            new JsonField('result', 'result', [], []),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class),
            new ManyToOneAssociationField('profile', 'profile_id', ImportExportProfileDefinition::class, 'id'),
            new OneToOneAssociationField('file', 'file_id', 'id', ImportExportFileDefinition::class, false),
            new OneToOneAssociationField('invalidRecordsLog', 'invalid_records_log_id', 'id', ImportExportLogDefinition::class, false),
            new OneToOneAssociationField('failedImportLog', 'id', 'invalid_records_log_id', ImportExportLogDefinition::class),
        ];

        return new FieldCollection($fields);
    }
}
