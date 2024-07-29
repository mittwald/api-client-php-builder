<?php

namespace Mittwald\ApiToolsPHP\Command;

use OpenAI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommitMessage extends Command
{
    protected function configure()
    {
        $this->setName('generate-commit-message');
        $this->addOption('from-uncommitted', 'u', InputOption::VALUE_NONE, 'Generate commit message from uncommitted changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $diff = [];

        if ($input->getOption('from-uncommitted')) {
            exec("git diff", $diff);
        } else {
            exec("git diff HEAD^..HEAD", $diff);
        }

        $yourApiKey = getenv('OPENAI_API_KEY');
        $client = OpenAI::client($yourApiKey);

        $result = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You will be provided a Git diff. Generate a commit message from it, following the conventional commit message format. Provide the output in plain text, without any additional formatting.'],
                ['role' => 'user', 'content' => join("\n", $diff)],
            ],
        ]);

        echo $result->choices[0]->message->content;

        return 0;
    }
}