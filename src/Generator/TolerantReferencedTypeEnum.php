<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\ReferencedTypeEnum;
use Helmich\Schema2Class\Generator\SchemaToEnum;

/**
 * A referenced enum that tolerates values the generated enum does not know, by mapping them to a
 * fallback case instead of raising a ValueError. This keeps released clients working when new
 * values are added to an enum in the API schema. The fallback case is added to the generated enums
 * by appending {@see self::FallbackValue} to their schema (see ComponentGenerator).
 */
readonly class TolerantReferencedTypeEnum extends ReferencedTypeEnum
{
    public const FallbackValue = "__unknown__";

    public function inputMappingExpr(GeneratorRequest $req, string $expr, ?string $validateExpr): string
    {
        $fallbackCase = SchemaToEnum::enumCaseName(self::FallbackValue);

        return "(\\{$this->name()}::tryFrom({$expr}) ?? \\{$this->name()}::{$fallbackCase})";
    }
}
