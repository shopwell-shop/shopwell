<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class ThemeDependencies extends Struct
{
    /**
     * @var array<int, string>
     */
    protected array $dependentThemes = [];

    public function __construct(protected ?string $id = null)
    {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return array<int, string>
     */
    public function getDependentThemes(): array
    {
        return $this->dependentThemes;
    }

    /**
     * @param array<int, string> $dependentThemes
     */
    public function setDependentThemes(array $dependentThemes): void
    {
        $this->dependentThemes = $dependentThemes;
    }

    public function addDependentTheme(string $dependentThemeId): void
    {
        $this->dependentThemes[] = $dependentThemeId;
    }
}
