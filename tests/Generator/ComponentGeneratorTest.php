<?php
namespace Mittwald\ApiToolsPHP\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertThat;
use function PHPUnit\Framework\equalTo;

#[CoversClass(ComponentGenerator::class)]
class ComponentGeneratorTest extends TestCase
{
    public static function componentToClassNames()
    {
        return [
            ["de.mittwald.v1.foo.Bar", "Foo\\Bar"],
            ["de.mittwald.v1.lead-finder.Bar", "LeadFinder\\Bar"],
        ];
    }

    #[Test]
    #[DataProvider('componentToClassNames')]
    public function classNamesAreComputedCorrectlyFromComponentName(string $componentName, string $expectedClassName): void
    {
        $className = ComponentGenerator::componentNameToClassName($componentName);
        assertThat($className, equalTo($expectedClassName));
    }
}