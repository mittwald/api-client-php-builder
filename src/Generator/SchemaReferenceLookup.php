<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\ReferencedType;
use Helmich\Schema2Class\Generator\ReferencedTypeClass;
use Helmich\Schema2Class\Generator\ReferencedTypeEnum;
use Helmich\Schema2Class\Generator\ReferenceLookup;

class SchemaReferenceLookup implements ReferenceLookup
{
    public function __construct(
        private readonly Context $context,
        private readonly bool    $tolerantEnums = false,
    )
    {
    }

    private static function isUnionOfReferences(array $schema): bool
    {
        if (!isset($schema["oneOf"])) {
            return false;
        }

        foreach ($schema["oneOf"] as $oneOf) {
            if (!isset($oneOf['$ref'])) {
                return false;
            }
        }

        return true;
    }

    private static function isUnionOfInlines(array $schema): bool
    {
        if (!isset($schema["oneOf"])) {
            return false;
        }

        foreach ($schema["oneOf"] as $oneOf) {
            if (isset($oneOf['$ref'])) {
                return false;
            }
        }

        return true;
    }

    private function buildUnionType(string $fqcn, array $types): ReferencedType
    {
        /** @var ReferencedType[] $innerRefs */
        $innerRefs = [];

        foreach ($types as $i => $alt) {
            if (isset($alt['$ref'])) {
                $innerRefs[] = $this->lookupReference($alt['$ref']);
            } else {
                $innerRefs[] = $this->buildTypeReference($fqcn . 'Alternative' . ($i + 1), $alt);
            }
        }

        return new ReferencedUnion($innerRefs);
    }

    public function lookupReference(string $reference): ReferencedType
    {
        [, , $componentType, $name] = explode("/", $reference);

        $componentNamespace = ucfirst($componentType);
        $baseNamespace      = "Mittwald\\ApiClient\\Generated\\V{$this->context->version}\\{$componentNamespace}";

        $className = ComponentGenerator::componentNameToClassName($name);
        $fqcn      = $baseNamespace . "\\" . $className;

        $schema = $this->context->schema["components"][$componentType][$name];
        return $this->buildTypeReference($fqcn, $schema);
    }

    public function lookupSchema(string $reference): array
    {
        [, , $componentType, $name] = explode("/", $reference);
        return $this->context->schema["components"][$componentType][$name];
    }

    private function buildEnumReference(string $fqcn, array $schema): ReferencedTypeEnum
    {
        if ($this->tolerantEnums && ($schema["type"] ?? null) === "string") {
            return new TolerantReferencedTypeEnum($fqcn);
        }

        return new ReferencedTypeEnum($fqcn);
    }

    private function buildTypeReference(string $fqcn, array $schema): ReferencedType
    {
        return match (true) {
            isset($schema["enum"]) => $this->buildEnumReference($fqcn, $schema),
            isset($schema["items"]["\$ref"]) => new ReferencedTypeList($this->lookupReference($schema["items"]["\$ref"])),
            isset($schema["items"]["enum"]) => new ReferencedTypeList($this->buildEnumReference($fqcn . "Item", $schema["items"])),
            isset($schema["items"]) => new ReferencedTypeList($this->buildTypeReference($fqcn . "Item", $schema["items"])),
            isset($schema["type"]) && $schema["type"] === "string" => new ReferencedString(),
            isset($schema["oneOf"]) => $this->buildUnionType($fqcn, $schema["oneOf"]),
            default => new ReferencedTypeClass($fqcn),
        };
    }
}