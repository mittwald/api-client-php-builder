<?php

namespace Mittwald\ApiToolsPHP\Generator;

use Helmich\Schema2Class\Spec\SpecificationOptions;

/**
 * GeneratorOptionsFactory is a helper class to build the options for the code
 * generator as specifically required by the Mittwald API client.
 */
class GeneratorOptionsFactory
{
    public static function buildGeneratorOptions(): SpecificationOptions
    {
        return (new SpecificationOptions())
            ->withTargetPHPVersion('8.2')
            ->withTreatValuesWithDefaultAsOptional(true)
            ->withNewValidatorClassExpr("new \Mittwald\ApiClient\Validator\Validator()");
    }
}