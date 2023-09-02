<?php

namespace Mittwald\ApiToolsPHP\Command;

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
        $schemaURL = $input->getArgument('schema');
        $outputPath = $input->getArgument('output');

        $output->writeln("Generating PHP classes from schema {$schemaURL} to {$outputPath}.");

        $schema = json_decode(file_get_contents($schemaURL), true);
        var_dump($schema);

        return 0;
    }


}