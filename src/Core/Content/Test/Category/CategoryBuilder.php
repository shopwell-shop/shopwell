<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Test\Category;

use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestBuilderTrait;

/**
 * @internal
 * How to use:
 *
 * $x = (new CategoryBuilder(new IdsCollection(), 'c1'))
 *          ->layout('my-custom-layout')
 *          ->slot('my-slot', ['media' => ['source' => 'static', value => 'some-uuid']])
 *          ->build();
 */
class CategoryBuilder
{
    use TestBuilderTrait;

    public string $id;

    protected ?string $parentId = null;

    protected ?string $name;

    /**
     * @var array<string, mixed>
     */
    protected array $customFields = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $translations = [];

    public function __construct(
        IdsCollection $ids,
        protected string $categoryName,
    ) {
        $this->ids = $ids;
        $this->id = $this->ids->create($categoryName);
        $this->name = $categoryName;
    }

    public function parent(string $key): self
    {
        $this->parentId = $this->ids->get($key);

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function customField(string $key, $value): self
    {
        $this->customFields[$key] = $value;

        return $this;
    }

    public function translation(string $languageId, string $key, mixed $value): self
    {
        $this->translations[$languageId][$key] = $value;

        return $this;
    }
}
