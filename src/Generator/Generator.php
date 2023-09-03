<?php

namespace Mittwald\ApiToolsPHP\Generator;

class Generator
{
    private Context $context;
    private ComponentGenerator $componentGenerator;
    private ClientGenerator $clientGenerator;

    public function __construct(Context $context, ComponentGenerator $componentGenerator, ClientGenerator $clientGenerator)
    {
        $this->componentGenerator = $componentGenerator;
        $this->clientGenerator = $clientGenerator;
        $this->context = $context;
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
        foreach ($this->context->schema["tags"] as $tag) {
            $clientNamespace = ucfirst(preg_replace("/[^a-zA-Z0-9]/", "", $tag["name"]));
            $baseNamespace   = "Mittwald\\ApiClient\\Generated\\V{$this->context->version}\\Clients\\{$clientNamespace}";

            $this->clientGenerator->generate($baseNamespace, $tag["name"]);
        }
    }

}