<?php

namespace Opus\Search\IndexBuilder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends AbstractIndexCommand
{

    protected static $defaultName = 'index:remove';

    /**
     * TODO update help text
     */
    protected function configure()
    {
        parent::configure();

        $help = 'TODO';

        $this->setName('index:remove')
            ->setDescription('Removes documents from search index')
            ->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('NOT IMPLEMENTED YET');
    }
}
