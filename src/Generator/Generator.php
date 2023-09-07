<?php

namespace Mittwald\ApiToolsPHP\Generator;

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
        $clients = [];

        foreach ($this->context->schema["tags"] as $tag) {
            $clientNamespace = ucfirst(preg_replace("/[^a-zA-Z0-9]/", "", $tag["name"]));
            $baseNamespace   = "Mittwald\\ApiClient\\Generated\\V{$this->context->version}\\Clients\\{$clientNamespace}";

            $this->clientGenerator->generate($baseNamespace, $tag);

            $clients[] = [$clientNamespace, $baseNamespace];
        }

        $this->clientFactoryGenerator->generate("Mittwald\\ApiClient\\Generated\\V{$this->context->version}", $clients);
    }

}