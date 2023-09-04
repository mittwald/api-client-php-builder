<?php
namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\ReferencedType;
use Helmich\Schema2Class\Generator\ReferencedTypeClass;
use Helmich\Schema2Class\Generator\ReferencedTypeEnum;
use Helmich\Schema2Class\Generator\ReferenceLookup;
use Helmich\Schema2Class\Generator\ReferenceLookupResult;
use Helmich\Schema2Class\Generator\ReferenceLookupResultType;
use Helmich\Schema2Class\Generator\SchemaToClassFactory;
use Helmich\Schema2Class\Spec\SpecificationOptions;
use Helmich\Schema2Class\Spec\ValidatedSpecificationFilesItem;
use Helmich\Schema2Class\Writer\DebugWriter;
use Helmich\Schema2Class\Writer\FileWriter;
use Symfony\Component\Console\Output\ConsoleOutput;

class ComponentGenerator
{
    private Context $context;
    private SchemaToClassFactory $s2c;

    public static function componentNameToClassName(string $name): string {
        $name = str_replace("de.mittwald.v1.", "", $name);
        $parts = explode(".", $name);
        $parts = array_map("ucfirst", $parts);

        return implode("\\", $parts);
    }

    public function __construct(Context $context, SchemaToClassFactory $s2c)
    {
        $this->context = $context;
        $this->s2c = new SchemaToClassFactory();
    }

    public function generate(string $baseNamespace, array $component, string $componentName): void
    {
        // Special treatment for inlined enums
        if (isset($component["items"]["enum"])) {
            $this->generate($baseNamespace, $component["items"], $componentName . "Item");
            return;
        }

        if (isset($component["oneOf"])) {
            foreach ($component["oneOf"] as $id => $oneOf) {
                if (!isset($oneOf['$ref'])) {
                    $this->generate($baseNamespace, $oneOf, $componentName . "Alternative" . ($id + 1));
                }
            }
            return;
        }

        if (!isset($component["properties"]) && !(isset($component["enum"]))) {
            trigger_error("Component {$componentName} is not an object, skipping.", E_USER_WARNING);
            return;
        }

        $className = $baseNamespace . "\\" . static::componentNameToClassName($componentName);
        $namespace = substr($className, 0, strrpos($className, "\\"));
        $classNameWithoutNamespace = substr($className, strrpos($className, "\\") + 1);
        $outputDir = GeneratorUtil::outputDirForClass($this->context, $className);

        $spec = new ValidatedSpecificationFilesItem($namespace, $classNameWithoutNamespace, $outputDir);
        $opts = (new SpecificationOptions())->withTargetPHPVersion("8.2");
        $request = new GeneratorRequest($component, $spec, $opts);
        $request = $request->withReferenceLookup(new SchemaReferenceLookup($this->context));
        $output = new ConsoleOutput();
        $writer = new FileWriter($output);

        try {
            $this->s2c->build($writer, $output)->schemaToClass($request);
        } catch (\Exception $e) {
            var_dump($component);
            throw $e;
        }
    }


}