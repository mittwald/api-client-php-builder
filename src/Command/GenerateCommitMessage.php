<?php

namespace Mittwald\ApiToolsPHP\Command;

use OpenAI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommitMessage extends Command
{
    private OpenAI\Client $client;

    protected function configure()
    {
        $this->setName('generate-commit-message');
        $this->addOption('from-uncommitted', 'u', InputOption::VALUE_NONE, 'Generate commit message from uncommitted changes');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $yourApiKey = getenv('OPENAI_API_KEY');
        $this->client = OpenAI::client($yourApiKey);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $diffLines = [];

        if ($input->getOption('from-uncommitted')) {
            exec("git diff", $diffLines);
        } else {
            exec("git diff HEAD^..HEAD", $diffLines);
        }

        $diff = join("\n", $diffLines);

        // When the diff is too long, simply truncate it to <256K and hope for the best
        // The theoretical message limit is 1MB, but there's also the maximum token limit to consider
        if (strlen($diff) > 256 * 1024) {
            $diff = substr($diff, 0, 256 * 1024);
        }

        $result = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You will be provided a Git diff. Generate a commit message from it, following the conventional commit message format. Provide the output in plain text, without any additional formatting.'],
                ['role' => 'user', 'content' => $diff],
            ],
        ]);

        echo $result->choices[0]->message->content;

        return 0;
    }
}