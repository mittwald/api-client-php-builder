<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\MatchGenerator;
use Helmich\Schema2Class\Generator\Property\NestedObjectProperty;
use Helmich\Schema2Class\Generator\Property\ReferenceProperty;
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
    private SchemaReferenceLookup $referenceLookup;

    public function __construct(private Context $context, SchemaToClassFactory $s2c = new SchemaToClassFactory())
    {
        $output                = new ConsoleOutput();
        $this->writer          = new FileWriter($output);
        $this->classBuilder    = $s2c->build($this->writer, $output);
        $this->referenceLookup = new SchemaReferenceLookup($this->context);
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

        $pathParameters = array_filter($operationData["parameters"] ?? [], fn(array $in): bool => $in["in"] === "path");

        $url = var_export($path, true);

        $body = "";

        $paramClassName      = ucfirst($methodName) . "Request";
        $paramClassNamespace = $namespace . "\\" . ucfirst($methodName);
        $paramClassNameFQ    = $paramClassNamespace . "\\" . $paramClassName;
        $outputDir           = GeneratorUtil::outputDirForClass($this->context, $paramClassNameFQ);

        $paramClassSchema = [
            "type"       => "object",
            "properties" => [],
            "required"   => [],
        ];

        $getUrlBody = "\$mapped = \$this->toJson();\n";

        foreach ($pathParameters as $param) {
            $paramClassSchema["properties"][$param["name"]] = $param["schema"];
            if ($param["required"]) {
                $paramClassSchema["required"][] = $param["name"];
            }

            $paramName    = $param["name"];
            $paramNameStr = var_export($paramName, true);
            $getUrlBody   .= "\${$paramName} = urlencode(\$mapped[{$paramNameStr}]);\n";

            $url = str_replace("{{$param["name"]}}", '\' . $' . $paramName . ' . \'', $url);
            $url = str_replace(" . ''", "", $url);
        }

        $getUrlBody   .= "return {$url};\n";
        $getUrlMethod = new MethodGenerator(name: "getUrl", body: $getUrlBody);
        $getUrlMethod->setReturnType("string");

        $parameterGenerators = [
            new ParameterGenerator(
                name: "request",
                type: $paramClassNameFQ,
            ),
        ];

        $req = new GeneratorRequest($paramClassSchema, new ValidatedSpecificationFilesItem($paramClassNamespace, $paramClassName, $outputDir), $generatorOpts);
        $req = $req->withAdditionalProperty(new PropertyGenerator(name: "method", defaultValue: $httpMethod, flags: PropertyGenerator::FLAG_PUBLIC | PropertyGenerator::FLAG_CONSTANT));
        $req = $req->withAdditionalMethod($getUrlMethod);

        $this->classBuilder->schemaToClass($req);

        $body .= "\$request = new Request(\\" . $paramClassNameFQ . "::method, \$request->getUrl());\n";
        $body .= "\$response = \$this->client->send(\$request);\n";

        $responseMatchBuilder = new MatchGenerator("\$response->getStatusCode()");
        $responses            = $operationData["responses"] ?? [];
        $responseTypes        = [];

        foreach ($responses as $statusCode => $response) {
            if (isset($response['$ref'])) {
                $response = $this->context->schema["components"]["responses"][str_replace("#/components/responses/", "", $response['$ref'])];
            }

            if (!isset($response["content"])) {
                $responseTypes[] = "null";
                $responseMatchBuilder->addArm($statusCode, "null");
                continue;
            }

            if (!isset($response["content"]["application/json"]["schema"])) {
                $responseTypes[] = "string";
                $responseMatchBuilder->addArm($statusCode, "\$response->getBody()");
                continue;
            }

            $responseSchema = $response["content"]["application/json"]["schema"];

            $responseClassName      = ucfirst($methodName) . $statusCode . "Response";
            $responseClassNamespace = $namespace . "\\" . ucfirst($methodName);
            $responseClassNameFQ    = $responseClassNamespace . "\\" . $responseClassName;
            $outputDir              = GeneratorUtil::outputDirForClass($this->context, $responseClassNameFQ);

            $req = new GeneratorRequest($responseSchema, new ValidatedSpecificationFilesItem($responseClassNamespace, $responseClassName, $outputDir), $generatorOpts);
            $req = $req->withReferenceLookup($this->referenceLookup);

            if (ReferenceProperty::canHandleSchema($responseSchema)) {
                $ref             = $this->referenceLookup->lookupReference($responseSchema['$ref']);
                $responseTypes[] = $ref->typeHint($req);
                $responseMatchBuilder->addArm($statusCode, $ref->inputMappingExpr($req, 'json_decode($response->getBody(), true)'));
                continue;
            }

            if (isset($responseSchema["type"]) && $responseSchema["type"] === "array") {
                $newResponseSchema = [
                    "type"       => "object",
                    "required"   => ["items"],
                    "properties" => [
                        "items" => $responseSchema,
                    ],
                ];

                $req = new GeneratorRequest($newResponseSchema, new ValidatedSpecificationFilesItem($responseClassNamespace, $responseClassName, $outputDir), $generatorOpts);
                $req = $req->withReferenceLookup($this->referenceLookup);

                $this->classBuilder->schemaToClass($req);

                $responseTypes[] = $responseClassNameFQ;
                $responseMatchBuilder->addArm($statusCode, "new \\{$responseClassNameFQ}(items: json_decode(\$response->getBody(), true))");
                continue;
            }

            if (!NestedObjectProperty::canHandleSchema($responseSchema)) {
                $responseTypes[] = "mixed";
                $responseMatchBuilder->addArm($statusCode, "json_decode(\$response->getBody(), true)");
                continue;
            }

            $this->classBuilder->schemaToClass($req);

            $responseTypes[] = $responseClassNameFQ;
            $responseMatchBuilder->addArm($statusCode, "\\{$responseClassNameFQ}::buildFromInput(json_decode(\$response->getBody(), true))");
        }

        $body .= "return " . $responseMatchBuilder->generate() . ";\n";

        $responseTypes = array_unique($responseTypes);
        if (in_array("mixed", $responseTypes)) {
            $responseTypes = ["mixed"];
        }

        $method = new MethodGenerator(name: $methodName);
        $method->setBody($body);
        $method->setParameters($parameterGenerators);
        $method->setReturnType(implode("|", $responseTypes));

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