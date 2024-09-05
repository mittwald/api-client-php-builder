<?php
namespace Mittwald\ApiToolsPHP\Generator;

readonly class ClientGenerationResult
{
    public function __construct(public bool $generated, public int $operationCount)
    {
    }
}