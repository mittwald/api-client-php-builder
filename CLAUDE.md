# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This is the **generator toolkit** that produces the mittwald PHP SDK ([mittwald/api-client-php](https://github.com/mittwald/api-client-php)) from the mStudio OpenAPI spec. The generated SDK itself lives in a separate repository, which gets cloned into `.work/` during generation. Code in this repo is the generator; code in `.work/` is generated output plus the hand-written parts of the SDK.

Requires PHP 8.5.

## Commands

```bash
composer install                # install dependencies
composer test                   # run generator unit tests (phpunit --testdox --color)
vendor/bin/phpunit --filter classNamesAreComputedCorrectlyFromComponentName   # run a single test

composer generate               # full run: clone/reset api-client-php into .work/, then regenerate
php ./cmd/generate.php generate https://api.mittwald.de/openapi ./.work -v    # generation only (needs existing .work/)
```

After generating, the output must be formatted and verified inside the target clone (formatting is slow and deliberately not part of `composer generate`):

```bash
cd .work && composer format && composer check && composer test
```

## Architecture

`cmd/generate.php` is a Symfony Console app with four commands (`src/Command/`):

- `generate` — the core pipeline (see below)
- `generate-commit-message` / `generate-release-notes` — feed a git diff to OpenAI (`gpt-4o-mini`, needs `OPENAI_API_KEY`); used by CI when publishing the generated SDK
- `version` — increments the patch part of a semver tag

### Generation pipeline (`src/Generator/`)

1. `GenerateCommand` fetches the OpenAPI schema (JSON) and wraps it with the output path in a `Context`. `Context->version` comes from the schema's `info.version` and determines the target namespace `Mittwald\ApiClient\Generated\V{version}\...`.
2. `GeneratorFactory` wires up `Generator`, which **deletes and regenerates** `src/Generated/V{n}/Schemas` and `.../Clients` in the output directory:
   - `ComponentGenerator` turns each entry in `components.schemas` into a PHP class using the `helmich/schema2class` library. Inline `items` and inline `oneOf` alternatives get their own classes (`FooItem`, `FooAlternative1`, ...).
   - `ClientGenerator` emits one client per OpenAPI **tag**: an interface (`FooClient`) plus implementation (`FooClientImpl`) with a method per operation, built with `laminas-code` generators. `sanitizeOutput()` regex-patches laminas-code quirks in the generated source. Tags without operations are skipped.
   - `ClientFactoryGenerator` emits the factory exposing all generated clients.
3. `SchemaReferenceLookup` resolves `$ref`s and `oneOf` unions across the schema so generated classes reference each other correctly.

### Naming

`ComponentGenerator::componentNameToClassName()` strips the `de.mittwald.v1.` prefix and maps dot-separated component names to sub-namespaces (`de.mittwald.v1.foo.Bar` → `Foo\Bar`). Casing exceptions for names that would otherwise be ucfirst'd wrongly (e.g. `aihosting` → `AIHosting`) live in `ComponentGenerator::$staticComponentNameMappings` — add new initialisms there. General word casing is handled by `src/Utils/Strings/ClassNameConverter.php`.

### schema2class dependency

The heavy lifting of class generation is done by `helmich/schema2class` (same author; local clone at `../php-schema2class`). When generation fails because of a library limitation, fix it there — for local development, the dependency can temporarily be switched to a composer path repository pointing at the clone (`{"type": "path", "url": "../php-schema2class", "options": {"symlink": true}}` plus a `dev-<branch>` constraint), but the constraint must go back to a tagged release before committing, since CI has no access to the clone.

### CI

- `validate.yml` (PRs / pushes to master): runs the unit tests, then a full generation against the live API and runs `composer check`/`test` in the generated client.
- `generate.yml` (daily + manual via `gh workflow run generate.yml`): regenerates from the production spec (→ `master`, tagged release with AI-generated notes) and the dev spec (→ `next` branch) and pushes to api-client-php.
