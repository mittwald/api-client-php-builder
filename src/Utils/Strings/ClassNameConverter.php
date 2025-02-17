<?php

namespace Mittwald\ApiToolsPHP\Utils\Strings;

class ClassNameConverter
{
    public static function toClassName(string $input): string
    {
        $words = preg_split('/[^a-zA-Z0-9]/', $input);
        $words = array_map('ucfirst', $words);
        return implode("", $words);
    }
}