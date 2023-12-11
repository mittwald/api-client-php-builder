<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Writer\FileWriter;
use Helmich\Schema2Class\Writer\WriterInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\GenericTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\InterfaceGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Mittwald\ApiToolsPHP\Utils\Strings\Lowercaser;
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
        $this->generateInterface($namespace, $clients);
        $this->generateImplementation($namespace, $clients);
    }

    private function generateInterface(string $namespace, array $clients): void
    {
        $ifaceName = "Client";
        $iface = new InterfaceGenerator(
            name: $ifaceName,
            namespaceName: $namespace,
        );

        $iface->setDocBlock(new DocBlockGenerator(
            shortDescription: "Auto-generated factory for mittwald mStudio v{$this->context->version} clients.",
            longDescription: CommentUtils::AutoGenerationNotice,
        ));

        foreach ($clients as [$clientName, $clientNamespace]) {
            $method = new MethodGenerator(name: Lowercaser::abbreviationAwareLowercase($clientName));
            $method->setReturnType("{$clientNamespace}\\{$clientName}Client");

            $iface->addMethodFromGenerator($method);
        }

        $file = new FileGenerator();
        $file->setClass($iface);
        $file->setNamespace($namespace);

        $outputDir  = GeneratorUtil::outputDirForClass($this->context, $namespace . "\\" . $ifaceName);
        $outputFile = $outputDir . "/" . $ifaceName . ".php";

        $this->writer->writeFile($outputFile, $file->generate());
    }

    private function generateImplementation(string $namespace, array $clients): void
    {
        $clsName = "ClientImpl";
        $cls     = new ClassGenerator(
            name: $clsName,
            namespaceName: $namespace,
            extends: "Mittwald\\ApiClient\\Client\\BaseClient",
            interfaces: ["{$namespace}\\Client"],
        );

        $cls->setDocBlock(new DocBlockGenerator(
            shortDescription: "Auto-generated factory for mittwald mStudio v{$this->context->version} clients.",
            longDescription: CommentUtils::AutoGenerationNotice,
        ));

        foreach ($clients as [$clientName, $clientNamespace]) {
            $method = new MethodGenerator(name: Lowercaser::abbreviationAwareLowercase($clientName));
            $method->setReturnType("{$clientNamespace}\\{$clientName}Client");
            $method->setBody("return new \\{$clientNamespace}\\{$clientName}ClientImpl(\$this->client);");

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