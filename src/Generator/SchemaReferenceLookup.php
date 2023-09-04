<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\ReferencedType;
use Helmich\Schema2Class\Generator\ReferencedTypeClass;
use Helmich\Schema2Class\Generator\ReferencedTypeEnum;
use Helmich\Schema2Class\Generator\ReferenceLookup;

class SchemaReferenceLookup implements ReferenceLookup
{
    public function __construct(private readonly Context $context)
    {
    }

    private static function isUnionOfReferences(array $schema): bool {
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

    public function lookupReference(string $reference): ReferencedType
    {
        [, , $componentType, $name] = explode("/", $reference);

        $componentNamespace = ucfirst($componentType);
        $baseNamespace      = "Mittwald\\ApiClient\\Generated\\V{$this->context->version}\\{$componentNamespace}";

        $className = ComponentGenerator::componentNameToClassName($name);
        $fqcn      = $baseNamespace . "\\" . $className;

        $schema = $this->context->schema["components"][$componentType][$name];

        return match (true) {
            isset($schema["enum"]) => new ReferencedTypeEnum($fqcn),
            isset($schema["items"]["\$ref"]) => new ReferencedTypeList($this->lookupReference($schema["items"]["\$ref"])),
            isset($schema["type"]) && $schema["type"] === "string" => new ReferencedString(),
            static::isUnionOfReferences($schema) => new ReferencedUnion(array_map(fn(array $schema) => $this->lookupReference($schema['$ref']), $schema["oneOf"])),
            default => new ReferencedTypeClass($fqcn),
        };
    }
}