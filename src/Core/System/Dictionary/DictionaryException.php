<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@discovery')]
class DictionaryException extends HttpException
{
    public const DICTIONARY_TYPE_NOT_FOUND = 'SYSTEM__DICTIONARY_TYPE_NOT_FOUND';

    public static function dictionaryTypeNotFound(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DICTIONARY_TYPE_NOT_FOUND,
            'Dictionary type with technical name "{{ technicalName }}" was not found.',
            ['technicalName' => $technicalName]
        );
    }

    public static function dictionaryEntryNotFound(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DICTIONARY_TYPE_NOT_FOUND,
            'Dictionary entry "not_specified" was not found for dictionary type "{{ technicalName }}".',
            ['technicalName' => $technicalName]
        );
    }
}
