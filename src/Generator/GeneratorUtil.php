<?php

namespace Mittwald\ApiToolsPHP\Generator;

class GeneratorUtil
{
    public static function outputDirForClass(Context $context, string $fqcn): string
    {
        $namespace = substr($fqcn, 0, strrpos($fqcn, "\\"));
        return $context->outputPath . "/src/" . str_replace("\\", "/", str_replace("Mittwald\\ApiClient\\", "", $namespace));
    }
}