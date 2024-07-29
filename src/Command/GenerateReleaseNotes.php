<?php

namespace Mittwald\ApiToolsPHP\Command;

use OpenAI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateReleaseNotes extends Command
{
    protected function configure()
    {
        $this->setName('generate-release-notes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $diff = [];

        exec("git diff HEAD^..HEAD", $diff);

        $yourApiKey = getenv('OPENAI_API_KEY');
        $client = OpenAI::client($yourApiKey);

        $result = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You will be provided a Git diff. Generate a Github release notes document, in markdown format. Your response should include ONLY the release notes without any additional start or end markers. Do not include a generic heading or date information; start any intermediate headings at the h2 level. When features are added, also include a high-level summary of said features.'],
                ['role' => 'user', 'content' => join("\n", $diff)],
            ],
        ]);

        echo $result->choices[0]->message->content;

        return 0;
    }
}