<?php
namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\MatchGenerator;
use Helmich\Schema2Class\Generator\ReferencedType;

class ReferencedUnion implements ReferencedType
{
    /**
     * @param ReferencedType[] $innerTypes
     */
    public function __construct(private readonly array $innerTypes)
    {
    }

    public function name(): string
    {
        return implode("|", array_map(fn(ReferencedType $type): string => $type->name(), $this->innerTypes));
    }

    public function typeAnnotation(GeneratorRequest $req): string
    {
        return implode("|", array_map(fn(ReferencedType $type): string => $type->typeAnnotation($req), $this->innerTypes));
    }

    public function typeHint(GeneratorRequest $req): ?string
    {
        return implode("|", array_map(fn(ReferencedType $type): string => $type->typeHint($req), $this->innerTypes));
    }

    public function serializedTypeHint(GeneratorRequest $req): ?string
    {
        $types = array_map(fn(ReferencedType $type): string => $type->serializedTypeHint($req), $this->innerTypes);
        $types = array_unique(array_filter($types));

        return implode("|", $types);
    }

    public function typeAssertionExpr(GeneratorRequest $req, string $expr): string
    {
        $exprs = array_map(fn(ReferencedType $type): string => $type->typeAssertionExpr($req, $expr), $this->innerTypes);
        $exprs = array_unique(array_filter($exprs));

        return '(' . implode(" || ", $exprs) . ')';
    }

    public function inputAssertionExpr(GeneratorRequest $req, string $expr): string
    {
        $exprs = array_map(fn(ReferencedType $type): string => $type->inputAssertionExpr($req, $expr), $this->innerTypes);
        $exprs = array_unique(array_filter($exprs));

        return '(' . implode(" || ", $exprs) . ')';
    }

    public function inputMappingExpr(GeneratorRequest $req, string $expr, ?string $validateExpr): string
    {
        $match = new MatchGenerator("true");
        $match->addArm("default", 'throw new \\InvalidArgumentException("input cannot be mapped to any valid type")');

        foreach ($this->innerTypes as $type) {
            $match->addArm(
                conditionExpr: $type->inputAssertionExpr($req, $expr),
                returnExpr: $type->inputMappingExpr($req, $expr, $validateExpr),
            );
        }

        return $match->generate();
    }

    public function outputMappingExpr(GeneratorRequest $req, string $expr): string
    {
        $match = new MatchGenerator("true");
        $match->addArm("default", 'throw new \\InvalidArgumentException("input cannot be mapped to any valid type")');

        foreach ($this->innerTypes as $type) {
            $match->addArm(
                conditionExpr: $type->typeAssertionExpr($req, $expr),
                returnExpr: $type->outputMappingExpr($req, $expr),
            );
        }

        return $match->generate();
    }

}