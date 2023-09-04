<?php
namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\MatchGenerator;
use Helmich\Schema2Class\Generator\ReferencedType;

class ReferencedString implements ReferencedType
{
    function name(): string
    {
        return "string";
    }

    function typeAnnotation(GeneratorRequest $req): string
    {
        return "string";
    }

    function typeHint(GeneratorRequest $req): ?string
    {
        return "string";
    }

    function serializedTypeHint(GeneratorRequest $req): ?string
    {
        return "string";
    }

    function typeAssertionExpr(GeneratorRequest $req, string $expr): string
    {
        return "is_string({$expr})";
    }

    function inputAssertionExpr(GeneratorRequest $req, string $expr): string
    {
        return "is_string({$expr})";
    }

    function inputMappingExpr(GeneratorRequest $req, string $expr, ?string $validateExpr): string
    {
        return $expr;
    }

    function outputMappingExpr(GeneratorRequest $req, string $expr): string
    {
        return $expr;
    }

}