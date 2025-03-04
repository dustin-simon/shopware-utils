<?php

namespace Dustin\ShopwareUtils\Core\Framework\Util;

class Util
{
    /**
     * This function exists because PHP's iterator_to_array() is buggy when using with generators
     */
    public static function iteratorToArray(iterable $data): array
    {
        $result = [];

        foreach($data as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }
}
