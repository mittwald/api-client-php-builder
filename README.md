# mittwald PHP-SDK utilities

> [!IMPORTANT]
> This repository contains tools for automatically generating the PHP-SDK. If you only want to use the mittwald mStudio API in your PHP project, there should be no need for you to interact with this project; please use the [mittwald PHP client](https://github.com/mittwald/api-client-php) in this case.

## Using the generator toolkit

### Automatic client generation

This repository is configured to automatically build and publish the PHP client using the `generate` GitHub Action. This action is triggered by a daily schedule, but can also be triggered manually:

```
$ gh workflow run generate.yml
```

### Generating the client locally

After cloning this repository, you can generate the client locally by running the following command:

```bash
$ composer install
$ composer generate
```

This will clone the latest `master` of [mittwald PHP client](https://github.com/mittwald/api-client-php) into the `.work` directory and re-generate the generated parts of the client.

After generating, you should switch into the `.work` directory, run the code formatting (not part of the `generate` command because it takes a long time) and commit the changes:

```bash
$ cd .work
$ composer install
$ composer format
$ git add .
$ git commit -m "Update generated client"
```