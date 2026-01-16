<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Mittwald\ApiToolsPHP\Utils\Filesystem;
use Mittwald\ApiToolsPHP\Utils\Strings\ClassNameConverter;

class Generator
{
    public function __construct(
        private Context                $context,
        private ComponentGenerator     $componentGenerator,
        private ClientGenerator        $clientGenerator,
        private ClientFactoryGenerator $clientFactoryGenerator,
    )
    {
    }

    public function generateComponents(): void
    {
        Filesystem::removeDirectoryRecursive($this->context->outputPath . "/src/Generated/V{$this->context->version}/Schemas");

        foreach (["schemas"] as $componentType) {
            $componentNamespace = ucfirst($componentType);
            $baseNamespace      = "Mittwald\\ApiClient\\Generated\\V{$this->context->version}\\{$componentNamespace}";

            foreach ($this->context->schema["components"][$componentType] as $componentName => $component) {
                $this->componentGenerator->generate($baseNamespace, $component, $componentName, $componentType);
            }
        }
    }

    public function generateClients(): void
    {
        Filesystem::removeDirectoryRecursive($this->context->outputPath . "/src/Generated/V{$this->context->version}/Clients");

        $clients = [];

        foreach ($this->context->schema["tags"] as $tag) {
            $clientNamespace = ClassNameConverter::toNamespaceName($tag["name"]);
            $baseNamespace   = "Mittwald\\ApiClient\\Generated\\V{$this->context->version}\\Clients\\{$clientNamespace}";

            $result = $this->clientGenerator->generate($baseNamespace, $tag);

            if ($result->generated) {
                $clients[] = [$clientNamespace, $baseNamespace];
            }
        }

        $this->clientFactoryGenerator->generate("Mittwald\\ApiClient\\Generated\\V{$this->context->version}", $clients);
    }

}