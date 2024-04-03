<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Generator\SchemaToClassFactory;

/**
 * GeneratorFactory is a factory class to build the Generator instance with all
 * required dependencies.
 */
class GeneratorFactory
{
    public static function build(Context $context): Generator
    {
        $generatorOpts = GeneratorOptionsFactory::buildGeneratorOptions();
        $schemaFactory = new SchemaToClassFactory();

        return new Generator(
            $context,
            new ComponentGenerator($context, $schemaFactory),
            new ClientGenerator($context, $generatorOpts, $schemaFactory),
            new ClientFactoryGenerator($context),
        );
    }
}