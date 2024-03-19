<?php
namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\Hook\ClassCreatedHook;
use Helmich\Schema2Class\Generator\Hook\EnumCreatedHook;
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
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\GenericTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\EnumGenerator\EnumGenerator;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @generated
 */
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
        // Special treatment for inlined item types
        if (isset($component["items"])) {
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

        if (!isset($component["properties"]) && !isset($component["enum"]) && !isset($component["allOf"])) {
            trigger_error("Component {$componentName} is not an object, skipping.", E_USER_WARNING);
            return;
        }

        $className = $baseNamespace . "\\" . static::componentNameToClassName($componentName);
        $namespace = substr($className, 0, strrpos($className, "\\"));
        $classNameWithoutNamespace = substr($className, strrpos($className, "\\") + 1);
        $outputDir = GeneratorUtil::outputDirForClass($this->context, $className);

        $spec = new ValidatedSpecificationFilesItem($namespace, $classNameWithoutNamespace, $outputDir);
        $opts = (new SpecificationOptions())
            ->withTargetPHPVersion("8.2")
            ->withTreatValuesWithDefaultAsOptional(true)
            ->withInlineAllofReferences(true)
            ->withNewValidatorClassExpr("new \Mittwald\ApiClient\Validator\Validator()");

        $request = new GeneratorRequest($component, $spec, $opts);
        $request = $request->withReferenceLookup(new SchemaReferenceLookup($this->context));
        $request = $request->withHook(new class($component, $componentName) implements ClassCreatedHook {
            function __construct(private readonly array $component, private readonly string $componentName) {}

            function onClassCreated(string $className, ClassGenerator $class): void
            {
                $docBlock = $class->getDocBlock() ?? new DocBlockGenerator();
                $docBlock->setShortDescription($this->component["description"] ?? "Auto-generated class for {$this->componentName}.");
                $docBlock->setLongDescription(CommentUtils::AutoGenerationNotice);
                $docBlock->setTag(new GenericTag("generated"));
                $docBlock->setTag(new GenericTag("see", CommentUtils::AutoGeneratorURL));

                $class->setDocBlock($docBlock);
            }
        });

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