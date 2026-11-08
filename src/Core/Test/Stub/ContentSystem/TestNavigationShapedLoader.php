<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * A navigation-shaped data loader: like the shipped `navigation` loader, its only `propertyReference`
 * key is defaulted, so a required reference wired through it resolves without ever demanding a stored input value;
 * it never raises `UnfilledRequiredInput`. Tagged `content_system.data_loader` in services_test.php. It produces
 * `MediaEntity` so it can wire onto the shipped `Sw:Media:Image` type's required `media` reference.
 *
 * @final
 *
 * @extends AbstractContentDataLoader<MediaEntity>
 */
#[Package('framework')]
class TestNavigationShapedLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'test_navigation_shaped';

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        // This loader produces MediaEntity like the built-in `entity` loader, but tier A never considers it: the
        // bare-string shorthand resolves only the two built-in resolvedBy loaders (entity/entity_collection),
        // closed by construction (ResolvedByLoaderBranch), so no third-party loader can ever compete for it.
        // Its one propertyReference key is defaulted, not required — mirroring the shipped `navigation` loader's
        // shape, so a required reference wired through it resolves without ever raising UnfilledRequiredInput.
        // Declared from literals only (the compiler pass dry-runs this on a constructor-less instance).
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'activeId'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        // Never invoked by the proofs: diagnostics resolves the produced type, it does not load data.
        return ContentDataLoaderResult::notFound();
    }
}
