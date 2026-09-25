<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\JWT\Struct;

use Shopwell\Core\Framework\JWT\JWTException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;
use Shopwell\Core\Framework\Validation\ValidatorFactory;

/**
 * @phpstan-import-type JSONWebKey from JWKStruct
 *
 * @extends Collection<JWKStruct>
 */
#[Package('checkout')]
class JWKCollection extends Collection
{
    /**
     * @param array{keys: array<int, JSONWebKey>} $data
     */
    public static function fromArray(array $data): self
    {
        $elements = ['elements' => \array_map(static function (array $element): JWKStruct {
            $dto = ValidatorFactory::create($element, JWKStruct::class);
            if (!$dto instanceof JWKStruct) {
                throw JWTException::invalidType(JWKStruct::class, $dto::class);
            }

            return $dto;
        }, $data['keys'])];

        return (new self())->assign($elements);
    }
}
