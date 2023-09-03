<?php

namespace Mittwald\ApiToolsPHP\Generator;

class Context
{
    public string $outputPath;
    public array $schema;
    public int $version;

    public function __construct(string $outputPath, array $schema)
    {
        $this->outputPath = $outputPath;
        $this->schema     = $schema;
        $this->version    = intval($this->schema["info"]["version"]);
    }
}