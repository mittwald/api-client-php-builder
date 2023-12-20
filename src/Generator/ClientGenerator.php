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
use Laminas\Code\Generator\DocBlock\Tag\GenericTag;
use Laminas\Code\Generator\DocBlock\Tag\ParamTag;
use Laminas\Code\Generator\DocBlock\Tag\ReturnTag;
use Laminas\Code\Generator\DocBlock\Tag\ThrowsTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\InterfaceGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\TypeGenerator;
use Mittwald\ApiToolsPHP\Utils\Strings\StatusTranslator;
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

    /**
     * @param array{description: string, name: string} $tag
     */
    public function generate(string $baseNamespace, array $tag): void
    {
        $ifaceName = ucfirst(preg_replace("/[^a-zA-Z0-9]/", "", $tag["name"])) . "Client";
        $clsName   = $ifaceName . "Impl";

        $operations       = $this->collectOperations($tag["name"]);
        $operationMethods = $this->buildOperationMethods($baseNamespace, $tag["name"], $operations);

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

        $clsComment = new DocBlockGenerator(
            shortDescription: "Client for {$tag["name"]} API",
            longDescription: $tag["description"] . "\n\n" . CommentUtils::AutoGenerationNotice,
            tags: [
                new GenericTag("generated"),
                new GenericTag("see", CommentUtils::AutoGeneratorURL),
            ],
        );
        $cls        = new ClassGenerator($clsName, $baseNamespace, properties: $props, methods: [$constructor, ...$operationMethods], interfaces: ["{$baseNamespace}\\{$ifaceName}"]);
        $cls->setDocBlock($clsComment);

        $ifaceMethods = array_map(fn(MethodGenerator $m) => clone $m, $operationMethods);

        $iface = new InterfaceGenerator($ifaceName, $baseNamespace, methods: $ifaceMethods);
        $iface->setDocBlock($clsComment);

        $clsFile = new FileGenerator();
        $clsFile->setClass($cls);
        $clsFile->setNamespace($baseNamespace);
        $clsFile->setUses([
            'GuzzleHttp\\Psr7\\Request',
        ]);

        $ifaceFile = new FileGenerator();
        $ifaceFile->setClass($iface);
        $ifaceFile->setNamespace($baseNamespace);

        $outputDir = GeneratorUtil::outputDirForClass($this->context, $baseNamespace . "\\" . $clsName);

        $clsContent   = self::sanitizeOutput($clsFile->generate(), $baseNamespace);
        $ifaceContent = self::sanitizeOutput($ifaceFile->generate(), $baseNamespace);

        $this->writer->writeFile("{$outputDir}/{$ifaceName}.php", $ifaceContent);
        $this->writer->writeFile("{$outputDir}/{$clsName}.php", $clsContent);
    }

    private function sanitizeOutput(string $content, string $baseNamespace): string
    {
        // Do some corrections because the Zend code generation library is stupid.
        $content = preg_replace('/ : \\\\self/', ' : self', $content);
        $content = preg_replace('/\\\\' . preg_quote($baseNamespace) . '\\\\/', '', $content);

        return $content;
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
        $pathParameters  = array_filter($operationData["parameters"] ?? [], fn(array $in): bool => $in["in"] === "path");
        $queryParameters = array_filter($operationData["parameters"] ?? [], fn(array $in): bool => $in["in"] === "query");

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

        $getUrlBody   = "\$mapped = \$this->toJson();\n";
        $getQueryBody = "\$mapped = \$this->toJson();\n\$query = [];\n";

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

        foreach ($queryParameters as $param) {
            $paramClassSchema["properties"][$param["name"]] = $param["schema"];
            if ($param["required"]) {
                $paramClassSchema["required"][] = $param["name"];
            }

            $paramName    = $param["name"];
            $paramNameStr = var_export($paramName, true);
            $getQueryBody .= "if (isset(\$mapped[{$paramNameStr}])) {\n    \$query[{$paramNameStr}] = \$mapped[{$paramNameStr}];\n}\n";
        }

        $getUrlBody   .= "return {$url};\n";
        $getUrlMethod = new MethodGenerator(name: "getUrl", body: $getUrlBody);
        $getUrlMethod->setReturnType("string");

        $getQueryBody   .= "return \$query;\n";
        $getQueryMethod = new MethodGenerator(name: "getQuery", body: $getQueryBody);
        $getQueryMethod->setReturnType("array");

        $getHeadersMethod = new MethodGenerator(name: "getHeaders", body: "return \$this->headers;\n");
        $getHeadersMethod->setReturnType("array");

        $withHeaderMethod = new MethodGenerator(
            name: "withHeader",
            parameters: [
                new ParameterGenerator(name: "name", type: TypeGenerator::fromTypeString("string")),
                new ParameterGenerator(name: "value", type: TypeGenerator::fromTypeString("string|array")),
            ],
            body: "\$clone = clone \$this;\n\$clone->headers[\$name] = \$value;\nreturn \$clone;",
        );
        $withHeaderMethod->setReturnType("self");

        $bodySchema = $operationData["requestBody"]["content"]["application/json"]["schema"] ?? null;
        if ($bodySchema !== null) {
            $paramClassSchema["properties"]["body"] = $bodySchema;
            $paramClassSchema["required"][]         = "body";
        }

        $methodProperty = new PropertyGenerator(name: "method", defaultValue: $httpMethod, flags: PropertyGenerator::FLAG_PUBLIC | PropertyGenerator::FLAG_CONSTANT);
        $headerProperty = new PropertyGenerator(name: "headers", defaultValue: [], flags: PropertyGenerator::FLAG_PRIVATE);
        $headerProperty->setType(TypeGenerator::fromTypeString("array"));

        $req = new GeneratorRequest($paramClassSchema, new ValidatedSpecificationFilesItem($paramClassNamespace, $paramClassName, $outputDir), $this->generatorOpts);
        $req = $req->withAdditionalProperty($methodProperty);
        $req = $req->withAdditionalProperty($headerProperty);
        $req = $req->withAdditionalMethod($getUrlMethod);
        $req = $req->withAdditionalMethod($getQueryMethod);
        $req = $req->withAdditionalMethod($getHeadersMethod);
        $req = $req->withAdditionalMethod($withHeaderMethod);
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
        $body       .= "\$httpResponse = \$this->client->send(\$httpRequest, [\n";
        $body       .= "    'query' => \$request->getQuery(),\n";
        $body       .= "    'headers' => \$request->getHeaders(),\n";

        if ($bodySchema !== null) {
            if (isset($bodySchema["type"]) && $bodySchema["type"] === "array") {
                $body .= "    'json' => \$request->toJson()['body'],\n";
            } else {
                $body .= "    'json' => \$request->getBody()->toJson(),\n";
            }
        }

        $body .= "]);\n";

        $responseMatchBuilder = new MatchGenerator("\$httpResponse->getStatusCode()");
        $responses            = $operationData["responses"] ?? [];

        /** @var OperationResponse[] $responseTypes */
        $responseTypes = [];

        foreach ($responses as $statusCode => $response) {
            if (isset($response['$ref'])) {
                $response = $this->context->schema["components"]["responses"][str_replace("#/components/responses/", "", $response['$ref'])];
            }

            if (!isset($response["content"])) {
                $responseTypes[] = new OperationResponse(
                    type: "\\Mittwald\\ApiClient\\Client\\EmptyResponse",
                    statusCode: $statusCode,
                    builderExpr: "new \\Mittwald\\ApiClient\\Client\\EmptyResponse(\$httpResponse)",
                    comment: $response["description"] ?? null,
                );
                continue;
            }

            if (!isset($response["content"]["application/json"]["schema"])) {
                $responseTypes[] = new OperationResponse(
                    type: "\\Mittwald\\ApiClient\\Client\\StringResponse",
                    statusCode: $statusCode,
                    builderExpr: "\\Mittwald\\ApiClient\\Client\\StringResponse::fromResponse(\$httpResponse)",
                    comment: $response["description"] ?? null,
                );
                continue;
            }

            $responseSchema = $response["content"]["application/json"]["schema"];

            $statusCodeAsText = $statusCode === "default" ? "Default" : StatusTranslator::statusCodeToText($statusCode);

            $responseClassName      = ucfirst($methodName) . $statusCodeAsText . "Response";
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

            $getResponseMethod = new MethodGenerator(
                name: "getResponse",
                body: "return \$this->httpResponse;",
            );
            $getResponseMethod->setReturnType("\\Psr\\Http\\Message\\ResponseInterface|null");

            $httpResponseProperty = new PropertyGenerator(
                name: "httpResponse",
                flags: PropertyGenerator::FLAG_PRIVATE,
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
            $req = $req->withAdditionalMethod($getResponseMethod);
            $req = $req->withAdditionalProperty($httpResponseProperty);
            $req = $req->withAdditionalInterface("\\Mittwald\\ApiClient\\Client\\ResponseContainer");

            if (!NestedObjectProperty::canHandleSchema($responseSchema) && !ReferenceProperty::canHandleSchema($responseSchema) && !ReferenceArrayProperty::canHandleSchema($responseSchema) && !ObjectArrayProperty::canHandleSchema($responseSchema)) {
                $responseTypes[] = new OperationResponse(
                    type: "\\Mittwald\\ApiClient\\Client\\UntypedResponse",
                    statusCode: $statusCode,
                    builderExpr: "\\Mittwald\\ApiClient\\Client\\UntypedResponse::fromResponse(\$httpResponse)",
                    comment: $response["description"] ?? null,
                );
                continue;
            }

            $this->classBuilder->schemaToClass($req);

            $responseTypes[] = new OperationResponse(
                type: '\\' . $responseClassNameFQ,
                statusCode: $statusCode,
                builderExpr: "\\{$responseClassNameFQ}::fromResponse(\$httpResponse)",
                comment: $response["description"] ?? null,
            );
        }

        $defaultResponse = OperationResponse::getSuccessfulResponse($responseTypes);
        $errorResponses  = OperationResponse::getUnsuccessfulResponses($responseTypes);

        foreach ($errorResponses as $responseType) {
            $responseMatchBuilder->addArm($responseType->statusCode, $responseType->builderExpr);
        }

        if ($defaultResponse !== null && is_int($defaultResponse->statusCode)) {
            $body .= "if (\$httpResponse->getStatusCode() === {$defaultResponse->statusCode}) {\n";
        } else if ($defaultResponse === null || $defaultResponse->statusCode === "default") {
            $body .= "if (\$httpResponse->getStatusCode() >= 200 && \$httpResponse->getStatusCode() < 300) {\n";
        }

        if ($defaultResponse === null) {
            $body .= "    return;\n";
        } else {
            $body .= "    return {$defaultResponse->builderExpr};\n";
        }
        $body .= "}\n";
        $body .= "throw new \\Mittwald\\ApiClient\\Error\\UnexpectedResponseException(" . $responseMatchBuilder->generate() . ");\n";

        $docComment = new DocBlockGenerator();
        $docComment->setShortDescription($operationData["summary"] ?? "Invoke the `{$operationId}` operation");
        $docComment->setTag(new GenericTag("see", CommentUtils::generateOperationLink($tag, $operationId)));
        $docComment->setTag(new ThrowsTag("\\GuzzleHttp\\Exception\\GuzzleException"));
        $docComment->setTag(new ThrowsTag("\\Mittwald\\ApiClient\\Error\\UnexpectedResponseException"));
        $docComment->setTag(new ParamTag("request", '\\' . $parameterClass, "An object representing the request for this operation"));
        $docComment->setWordWrap(false);

        $deprecatedByName = str_starts_with($operationId, "deprecated-");
        $deprecatedBySpec = isset($operationData["deprecated"]) && $operationData["deprecated"];

        if ($deprecatedByName || $deprecatedBySpec) {
            $docComment->setTag(new GenericTag("deprecated"));
        }

        if (isset($operationData["description"])) {
            $docComment->setLongDescription($operationData["description"]);
        }

        if ($defaultResponse !== null) {
            $docComment->setTag(new ReturnTag($defaultResponse->type, $defaultResponse->comment));
        }

        $method = new MethodGenerator(name: $methodName);
        $method->setBody($body);
        $method->setParameters($parameterGenerators);
        $method->setReturnType($defaultResponse ? $defaultResponse->type : "void");
        $method->setDocBlock($docComment);

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
                if (in_array($tag, $operationData["tags"]) && isset($operationData["operationId"])) {
                    $operations[] = [$path, $method, $operationData];
                }
            }
        }

        usort($operations, fn(array $a, array $b): int => strcmp($a[2]["operationId"], $b[2]["operationId"]));
        
        return $operations;
    }
}