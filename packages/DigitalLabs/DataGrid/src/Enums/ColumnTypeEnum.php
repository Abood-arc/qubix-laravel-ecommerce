<?php

namespace DigitalLabs\DataGrid\Enums;

use DigitalLabs\DataGrid\ColumnTypes\Aggregate;
use DigitalLabs\DataGrid\ColumnTypes\Boolean;
use DigitalLabs\DataGrid\ColumnTypes\Date;
use DigitalLabs\DataGrid\ColumnTypes\Datetime;
use DigitalLabs\DataGrid\ColumnTypes\Decimal;
use DigitalLabs\DataGrid\ColumnTypes\Integer;
use DigitalLabs\DataGrid\ColumnTypes\Text;
use DigitalLabs\DataGrid\Exceptions\InvalidColumnTypeException;

enum ColumnTypeEnum: string
{
    /**
     * String.
     */
    case STRING = 'string';

    /**
     * Integer.
     */
    case INTEGER = 'integer';

    /**
     * Decimal.
     */
    case DECIMAL = 'decimal';

    /**
     * Boolean.
     */
    case BOOLEAN = 'boolean';

    /**
     * Date.
     */
    case DATE = 'date';

    /**
     * Date time.
     */
    case DATETIME = 'datetime';

    /**
     * Aggregate.
     */
    case AGGREGATE = 'aggregate';

    /**
     * Get the corresponding class name for the column type.
     */
    public static function getClassName(string $type): string
    {
        return match ($type) {
            self::STRING->value => Text::class,
            self::INTEGER->value => Integer::class,
            self::DECIMAL->value => Decimal::class,
            self::BOOLEAN->value => Boolean::class,
            self::DATE->value => Date::class,
            self::DATETIME->value => Datetime::class,
            self::AGGREGATE->value => Aggregate::class,
            default => throw new InvalidColumnTypeException("Invalid column type: {$type}"),
        };
    }
}
