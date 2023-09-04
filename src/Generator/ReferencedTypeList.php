<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\ReferencedType;

class ReferencedTypeList implements ReferencedType
{
    public function __construct(private readonly ReferencedType $innerType)
    {
    }

    function name(): string
    {
        return "{$this->innerType->name()}[]";
    }

    function typeAnnotation(GeneratorRequest $req): string
    {
        return "{$this->innerType->typeAnnotation($req)}[]";
    }

    function typeHint(GeneratorRequest $req): ?string
    {
        return "array";
    }

    function serializedTypeHint(GeneratorRequest $req): ?string
    {
        return "array";
    }

    function typeAssertionExpr(GeneratorRequest $req, string $expr): string
    {
        $map = "array_map(fn(\$item): bool => {$this->innerType->typeAssertionExpr($req, '$item')}, $expr)";
        return "array_reduce($map, fn(\$carry, \$item): bool => \$carry && \$item, true)";
    }

    function inputAssertionExpr(GeneratorRequest $req, string $expr): string
    {
        $map = "array_map(fn(\$item): bool => {$this->innerType->inputAssertionExpr($req, '$item')}, $expr)";
        return "array_reduce($map, fn(\$carry, \$item): bool => \$carry && \$item, true)";
    }

    function inputMappingExpr(GeneratorRequest $req, string $expr, ?string $validateExpr): string
    {
        $innerType = $this->innerType->typeHint($req);
        if ($innerType) {
            return "array_map(fn(\$item): {$innerType} => {$this->innerType->inputMappingExpr($req, '$item', $validateExpr)}, $expr)";
        }
        return "array_map(fn(\$item) => {$this->innerType->inputMappingExpr($req, '$item', $validateExpr)}, $expr)";
    }

    function outputMappingExpr(GeneratorRequest $req, string $expr): string
    {
        if ($req->isAtLeastPHP("8.0")) {
            return "array_map(fn(\$item): {$this->innerType->serializedTypeHint($req)} => {$this->innerType->outputMappingExpr($req, '$item')}, $expr)";
        }
        return "array_map(fn(\$item): bool => {$this->innerType->outputMappingExpr($req, '$item')}, $expr)";
    }


}