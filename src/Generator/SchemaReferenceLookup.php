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
            default => new ReferencedTypeClass($fqcn),
        };
    }
}