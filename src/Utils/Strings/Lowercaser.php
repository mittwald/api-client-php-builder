<?php

namespace Mittwald\ApiToolsPHP\Utils\Strings;

class Lowercaser
{
    private static $commonAbbreviations = [
        "SSH",
        "SSL",
        "API",
        "URL",
        "URI",
        "UUID",
        "DNS",
        "HTTPS",
        "HTTP",
        "FTP",
        "SFTP",

    ];

    public static function abbreviationAwareLowercase(string $input): string
    {
        foreach (self::$commonAbbreviations as $abbreviation) {
            if (str_starts_with(strtoupper($input), $abbreviation)) {
                return strtolower($abbreviation) . substr($input, strlen($abbreviation));
            }
        }

        return lcfirst($input);
    }
}