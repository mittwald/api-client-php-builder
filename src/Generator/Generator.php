<?php

namespace Mittwald\ApiToolsPHP\Generator;

class Generator
{
    private Context $context;
    private ComponentGenerator $componentGenerator;

    public function __construct(Context $context, ComponentGenerator $componentGenerator)
    {
        $this->componentGenerator = $componentGenerator;
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

}