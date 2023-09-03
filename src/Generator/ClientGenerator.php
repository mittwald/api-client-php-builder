<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\PropertyBuilder;
use Helmich\Schema2Class\Generator\SchemaToClass;
use Helmich\Schema2Class\Generator\SchemaToClassFactory;
use Helmich\Schema2Class\Spec\SpecificationOptions;
use Helmich\Schema2Class\Spec\ValidatedSpecificationFilesItem;
use Helmich\Schema2Class\Writer\FileWriter;
use Helmich\Schema2Class\Writer\WriterInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\TypeGenerator;
use Symfony\Component\Console\Output\ConsoleOutput;

class ClientGenerator
{
    private SchemaToClass $classBuilder;
    private WriterInterface $writer;

    public function __construct(private Context $context, SchemaToClassFactory $s2c = new SchemaToClassFactory())
    {
        $output             = new ConsoleOutput();
        $this->writer       = new FileWriter($output);
        $this->classBuilder = $s2c->build($this->writer, $output);
    }

    public function generate(string $baseNamespace, string $tag): void
    {
        $clsName = ucfirst(preg_replace("/[^a-zA-Z0-9]/", "", $tag)) . "Client";

        $operations       = $this->collectOperations($tag);
        $operationMethods = $this->buildOperationMethods($baseNamespace, $tag, $operations);

        $constructor = new MethodGenerator(
            name: "__construct",
            parameters: [
                new ParameterGenerator(name: "client", type: TypeGenerator::fromTypeString("\\GuzzleHttp\\Client")),
            ],
            body: '$this->client = $client;'
        );

        $clientProp = new PropertyGenerator(name: "client", flags: PropertyGenerator::FLAG_PRIVATE, type: TypeGenerator::fromTypeString("\\GuzzleHttp\\Client"));
        $clientProp->omitDefaultValue();

        $props = [$clientProp];

        $cls = new ClassGenerator($clsName, $baseNamespace, properties: $props, methods: [$constructor, ...$operationMethods]);

        $file = new FileGenerator();
        $file->setClass($cls);
        $file->setNamespace($baseNamespace);
        $file->setUses([
            'GuzzleHttp\\Psr7\\Request',
        ]);

        $outputDir = GeneratorUtil::outputDirForClass($this->context, $baseNamespace . "\\" . $clsName);

        $content = $file->generate();

        // Do some corrections because the Zend code generation library is stupid.
        $content = preg_replace('/ : \\\\self/', ' : self', $content);
        $content = preg_replace('/\\\\' . preg_quote($baseNamespace) . '\\\\/', '', $content);

        $this->writer->writeFile("{$outputDir}/{$clsName}.php", $content);
    }

    private function buildOperationMethods(string $namespace, string $tag, array $operations): array
    {
        $methods = [];
        foreach ($operations as [$path, $method, $operationData]) {
            if (!isset($operationData["operationId"])) {
                continue;
            }

            $methods[] = $this->buildOperationMethod($namespace, $tag, $path, $method, $operationData);
        }
        return $methods;
    }

    private function buildOperationMethod(string $namespace, string $tag, string $path, string $httpMethod, array $operationData): MethodGenerator
    {
        $operationId = $operationData["operationId"];
        $methodName  = $this->mapOperationId($tag, $operationId);

        $httpMethod    = strtoupper($httpMethod);
        $generatorOpts = (new SpecificationOptions())
            ->withTargetPHPVersion("8.2");

        $pathParameters          = array_filter($operationData["parameters"] ?? [], fn(array $in): bool => $in["in"] === "path");
        $pathParameterGenerators = [];

        $url = var_export($path, true);

        $body = "";

        $paramClassName   = ucfirst($methodName) . "Request";
        $paramClassNameFQ = $namespace . "\\" . $paramClassName;
        $outputDir        = GeneratorUtil::outputDirForClass($this->context, $paramClassNameFQ);

        foreach ($pathParameters as $param) {
            $req = new GeneratorRequest($param, new ValidatedSpecificationFilesItem($namespace, $paramClassName, $outputDir), $generatorOpts);

            $prop = PropertyBuilder::buildPropertyFromSchema($req, $param["name"], $param["schema"], true);
            $prop->generateSubTypes($this->classBuilder);

            $paramNameForUrl = $param["name"] . "ForUrl";

            $body .= "\${$paramNameForUrl} = urlencode(" . $prop->generateOutputMappingExpr("\${$param["name"]}") . ");\n";

            $url = str_replace("{{$param["name"]}}", '\' . $' . $paramNameForUrl . ' . \'', $url);
            $url = str_replace(" . ''", "", $url);

            $pathParameterGenerators[] = new ParameterGenerator(
                name: $prop->key(),
                type: $prop->typeHint("8.2"),
            );
        }

        $body .= "\$request = new Request(" . var_export($httpMethod, true) . ", {$url});\n";
        $body .= "\$response = \$this->client->send(\$request);\n";

        $method = new MethodGenerator(name: $methodName);
        $method->setBody($body);
        $method->setParameters($pathParameterGenerators);

        return $method;
    }

    private function mapOperationId(string $tag, string $operationId): string
    {
        $lowerTag = preg_quote(strtolower($tag), "/");

        $operationId = preg_replace("/^{$lowerTag}-/", "", $operationId);
        $operationId = preg_replace("/^(ssh|sftp)-user-/", "", $operationId);
        $operationId = str_replace("-", " ", $operationId);
        $operationId = ucwords($operationId);
        $operationId = str_replace(" ", "", $operationId);
        $operationId = lcfirst($operationId);

        return $operationId;
    }

    private function collectOperations(string $tag): array
    {
        $operations = [];
        foreach ($this->context->schema["paths"] as $path => $pathOperations) {
            foreach ($pathOperations as $method => $operationData) {
                if (in_array($tag, $operationData["tags"])) {
                    $operations[] = [$path, $method, $operationData];
                }
            }
        }
        return $operations;
    }
}