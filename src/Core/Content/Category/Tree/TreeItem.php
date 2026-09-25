<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Tree;

use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class TreeItem extends Struct
{
    /**
     * @internal public to allow AfterSort::sort()
     */
    public ?string $afterId = null;

    /**
     * @param TreeItem[] $children
     */
    public function __construct(
        protected ?CategoryEntity $category,
        protected array $children,
    ) {
        $this->afterId = $this->category?->getAfterCategoryId();
    }

    public function getId(): string
    {
        return $this->getCategory()->getId();
    }

    public function setCategory(CategoryEntity $category): void
    {
        $this->category = $category;
        $this->afterId = $category->getAfterCategoryId();
    }

    public function getCategory(): CategoryEntity
    {
        if (!$this->category) {
            throw CategoryException::categoryNotFound('treeItem');
        }

        return $this->category;
    }

    /**
     * @return TreeItem[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChildren(TreeItem ...$items): void
    {
        foreach ($items as $item) {
            $this->children[] = $item;
        }
    }

    /**
     * @param TreeItem[] $children
     */
    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function getApiAlias(): string
    {
        return 'category_tree_item';
    }
}
