<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField;

class CustomFieldTypes
{
    public const BOOL = 'bool';
    public const COLORPICKER = 'colorpicker';
    public const DATETIME = 'datetime';
    public const ENTITY = 'entity';
    public const FLOAT = 'float';
    public const INT = 'int';
    public const JSON = 'json';
    public const PRICE = 'price';
    public const HTML = 'html';
    public const MEDIA = 'media';
    public const SELECT = 'select';
    public const TEXT = 'text';

    public const TYPES = [
        self::BOOL,
        self::COLORPICKER,
        self::DATETIME,
        self::ENTITY,
        self::FLOAT,
        self::INT,
        self::JSON,
        self::PRICE,
        self::HTML,
        self::MEDIA,
        self::SELECT,
        self::TEXT,
    ];
}
