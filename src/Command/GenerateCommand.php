<?php

namespace Mittwald\ApiToolsPHP\Command;

use Mittwald\ApiToolsPHP\Generator\Context;
use Mittwald\ApiToolsPHP\Generator\GeneratorFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('generate');
        $this->addArgument('schema', InputArgument::REQUIRED, 'URL to schema');
        $this->addArgument('output', InputArgument::REQUIRED, 'Output directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaURL  = $input->getArgument('schema');
        $outputPath = $input->getArgument('output');

        $output->writeln("Generating PHP classes from schema {$schemaURL} to {$outputPath}.");

        $schema = json_decode(file_get_contents($schemaURL), associative: true);

        $generator = GeneratorFactory::build(new Context($outputPath, $schema));
        $generator->generateComponents();
        $generator->generateClients();

        return 0;
    }
}