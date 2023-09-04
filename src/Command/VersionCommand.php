<?php

namespace Mittwald\ApiToolsPHP\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends Command
{
    protected function configure(): void
    {
        $this->setName("version");
        $this->addArgument(name: "version", mode: InputArgument::REQUIRED, description: "previous version to increment");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getArgument("version");
        $versionParts = explode(".", $version);
        $versionParts[count($versionParts) - 1] = (int) $versionParts[count($versionParts) - 1] + 1;
        $newVersion = implode(".", $versionParts);
        $output->writeln($newVersion);
        return 0;
    }
}