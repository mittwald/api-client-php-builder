<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Writer\FileWriter;
use Helmich\Schema2Class\Writer\WriterInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\GenericTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Symfony\Component\Console\Output\ConsoleOutput;

class ClientFactoryGenerator
{
    private WriterInterface $writer;

    public function __construct(private readonly Context $context)
    {
        $output       = new ConsoleOutput();
        $this->writer = new FileWriter($output);
    }

    public function generate(string $namespace, array $clients): void
    {
        $clsName = "Client";
        $cls     = new ClassGenerator(
            name: $clsName,
            namespaceName: $namespace,
            extends: "Mittwald\\ApiClient\\Client\\BaseClient",
        );

        $cls->setDocBlock(new DocBlockGenerator(
            shortDescription: "Auto-generated factory for mittwald mStudio v{$this->context->version} clients.",
            longDescription: CommentUtils::AutoGenerationNotice,
        ));

        foreach ($clients as [$clientName, $clientNamespace]) {
            $method = new MethodGenerator(name: lcfirst($clientName));
            $method->setReturnType("{$clientNamespace}\\{$clientName}Client");
            $method->setBody("return new \\{$clientNamespace}\\{$clientName}Client(\$this->client);");

            $cls->addMethodFromGenerator($method);
        }

        $file = new FileGenerator();
        $file->setClass($cls);
        $file->setNamespace($namespace);

        $outputDir  = GeneratorUtil::outputDirForClass($this->context, $namespace . "\\" . $clsName);
        $outputFile = $outputDir . "/" . $clsName . ".php";

        $this->writer->writeFile($outputFile, $file->generate());
    }
}