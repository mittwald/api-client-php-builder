<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\MatchGenerator;
use Helmich\Schema2Class\Generator\Property\NestedObjectProperty;
use Helmich\Schema2Class\Generator\Property\ObjectArrayProperty;
use Helmich\Schema2Class\Generator\Property\ReferenceArrayProperty;
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

    public function __construct(
        private readonly Context              $context,
        private readonly SpecificationOptions $generatorOpts,
        SchemaToClassFactory                  $s2c = new SchemaToClassFactory(),
    )
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

    private function buildOperationRequestClass(string $namespace, string $methodName, string $httpMethod, string $path, array $operationData): string
    {
        $pathParameters = array_filter($operationData["parameters"] ?? [], fn(array $in): bool => $in["in"] === "path");

        $url = var_export($path, true);

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

        $bodySchema = $operationData["requestBody"]["content"]["application/json"]["schema"] ?? null;
        if ($bodySchema !== null) {
            $paramClassSchema["properties"]["body"] = $bodySchema;
            $paramClassSchema["required"][]         = "body";
        }

        $methodProperty = new PropertyGenerator(name: "method", defaultValue: $httpMethod, flags: PropertyGenerator::FLAG_PUBLIC | PropertyGenerator::FLAG_CONSTANT);

        $req = new GeneratorRequest($paramClassSchema, new ValidatedSpecificationFilesItem($paramClassNamespace, $paramClassName, $outputDir), $this->generatorOpts);
        $req = $req->withAdditionalProperty($methodProperty);
        $req = $req->withAdditionalMethod($getUrlMethod);
        $req = $req->withReferenceLookup($this->referenceLookup);

        $this->classBuilder->schemaToClass($req);
        return $paramClassNameFQ;
    }

    private function buildOperationMethod(string $namespace, string $tag, string $path, string $httpMethod, array $operationData): MethodGenerator
    {
        $operationId = $operationData["operationId"];
        $methodName  = $this->mapOperationId($tag, $operationId);

        $parameterClass      = $this->buildOperationRequestClass($namespace, $methodName, $httpMethod, $path, $operationData);
        $parameterGenerators = [
            new ParameterGenerator(
                name: "request",
                type: $parameterClass,
            ),
        ];

        $body = "\$httpRequest = new Request(\\" . $parameterClass . "::method, \$request->getUrl());\n";

        $bodySchema = $operationData["requestBody"]["content"]["application/json"]["schema"] ?? null;
        if ($bodySchema !== null) {
            if (isset($bodySchema["type"]) && $bodySchema["type"] === "array") {
                $body .= "\$httpResponse = \$this->client->send(\$httpRequest, ['json' => \$request->toJson()['body']]);\n";
            } else {
                $body .= "\$httpResponse = \$this->client->send(\$httpRequest, ['json' => \$request->getBody()->toJson()]);\n";
            }
        } else {
            $body .= "\$httpResponse = \$this->client->send(\$httpRequest);\n";
        }

        $responseMatchBuilder = new MatchGenerator("\$httpResponse->getStatusCode()");
        $responses            = $operationData["responses"] ?? [];
        $responseTypes        = [];

        foreach ($responses as $statusCode => $response) {
            if (isset($response['$ref'])) {
                $response = $this->context->schema["components"]["responses"][str_replace("#/components/responses/", "", $response['$ref'])];
            }

            if (!isset($response["content"])) {
                $responseTypes[] = "\\Mittwald\\ApiClient\\Client\\EmptyResponse";
                $responseMatchBuilder->addArm($statusCode, "new \\Mittwald\\ApiClient\\Client\\EmptyResponse(\$httpResponse)");
                continue;
            }

            if (!isset($response["content"]["application/json"]["schema"])) {
                $responseTypes[] = "string";
                $responseMatchBuilder->addArm($statusCode, "\$httpResponse->getBody()");
                continue;
            }

            $responseSchema = $response["content"]["application/json"]["schema"];

            $responseClassName      = ucfirst($methodName) . ($statusCode === "default" ? "Default" : $statusCode) . "Response";
            $responseClassNamespace = $namespace . "\\" . ucfirst($methodName);
            $responseClassNameFQ    = $responseClassNamespace . "\\" . $responseClassName;
            $outputDir              = GeneratorUtil::outputDirForClass($this->context, $responseClassNameFQ);

            $factoryMethod = new MethodGenerator(
                name: "fromResponse",
                parameters: [
                    new ParameterGenerator(name: "httpResponse", type: TypeGenerator::fromTypeString("\\Psr\\Http\\Message\\ResponseInterface")),
                ],
                flags: MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
                body: "\$parsedBody = json_decode(\$httpResponse->getBody()->getContents(), associative: true);\n" .
                "\$response = static::buildFromInput(['body' => \$parsedBody], validate: false);\n" .
                "\$response->httpResponse = \$httpResponse;\n" .
                "return \$response;",
            );
            $factoryMethod->setReturnType("self");

            $httpResponseProperty = new PropertyGenerator(
                name: "httpResponse",
                type: TypeGenerator::fromTypeString("\\Psr\\Http\\Message\\ResponseInterface|null"),
            );

            $envelopedResponseSchema = [
                "type"       => "object",
                "required"   => ["body"],
                "properties" => [
                    "body" => $responseSchema,
                ],
            ];

            $req = new GeneratorRequest($envelopedResponseSchema, new ValidatedSpecificationFilesItem($responseClassNamespace, $responseClassName, $outputDir), $this->generatorOpts);
            $req = $req->withReferenceLookup($this->referenceLookup);
            $req = $req->withAdditionalMethod($factoryMethod);
            $req = $req->withAdditionalProperty($httpResponseProperty);

            if (!NestedObjectProperty::canHandleSchema($responseSchema) && !ReferenceProperty::canHandleSchema($responseSchema) && !ReferenceArrayProperty::canHandleSchema($responseSchema) && !ObjectArrayProperty::canHandleSchema($responseSchema)) {
                $responseTypes[] = "\\Mittwald\\ApiClient\\Client\\UntypedResponse";
                $responseMatchBuilder->addArm($statusCode, "\\Mittwald\\ApiClient\\Client\\UntypedResponse::fromResponse(\$httpResponse)");
                continue;
            }

            $this->classBuilder->schemaToClass($req);

            $responseTypes[] = $responseClassNameFQ;
            $responseMatchBuilder->addArm($statusCode, "\\{$responseClassNameFQ}::fromResponse(\$httpResponse)");
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