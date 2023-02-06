<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the North Rhine-Westphalian Library Service Center,
 * the Cooperative Library Network Berlin-Brandenburg, the Saarland University
 * and State Library, the Saxon State Library - Dresden State and University
 * Library, the Bielefeld University Library and the University Library of
 * Hamburg University of Technology with funding from the German Research
 * Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @copyright   Copyright (c) 2020, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Console;

use Opus\Common\Console\AbstractBaseDocumentCommand;
use Opus\Common\Model\ModelException;
use Opus\Search\Console\Helper\IndexHelper;
use Opus\Search\SearchException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Config_Exception;

use function ctype_digit;
use function ltrim;
use function sprintf;

class IndexCommand extends AbstractBaseDocumentCommand
{
    const OPTION_BLOCKSIZE = 'blocksize';

    const OPTION_CLEAR_CACHE = 'clear-cache';

    const OPTION_REMOVE = 'remove';

    const OPTION_TIMEOUT = 'timeout';

    /** @var string */
    protected static $defaultName = 'index:index';

    /** @var int */
    protected $blockSize = 10;

    protected function configure()
    {
        parent::configure();

        $help = <<<EOT
The <fg=green>index:index</> (short <fg=green>i:i</>) command can be used to index a single document or a 
range of documents.

If no <fg=green>ID</> is provided, all documents will be indexed. Before the indexing starts, 
all documents will be removed from the search index.   

You can use a dash (<fg=yellow>-</>) as <fg=green>StartID</> or <fg=green>EndID</>, if you want to index all documents up 
to or starting from an ID. 

Examples:
  <fg=yellow></>        will index all documents 
  <fg=yellow>50</>      will index document 50
  <fg=yellow>20 60</>   will index documents 20 to 60
  <fg=yellow>20 -</>    will index all documents starting from 20
  <fg=yellow>- 50</>    will index all documents up to 50
  
You can use the <fg=green>blocksize</> option to specify how many documents should be indexed 
in a single request to the Solr server. Indexing multiple documents per request 
improves performance. However sometimes this can cause problems if the indexing 
fails for one of the documents included in a block. In that case you can set
the <fg=green>blocksize</> to <fg=yellow>1</> in order to index every document separately.

Using <fg=green>--verbose</> (<fg=green>-v</>) will show the lowest and highest document ID found in the 
specified range.
EOT;

        $this->setName('index:index')
            ->setDescription('Indexes documents')
            ->setHelp($help)
            ->addOption(
                self::OPTION_BLOCKSIZE,
                'b',
                InputOption::VALUE_REQUIRED,
                'Max number of documents indexed together',
                10
            )
            ->addOption(
                self::OPTION_CLEAR_CACHE,
                'c',
                null,
                'Clear document XML cache entries before indexing'
            )
            ->addOption(
                self::OPTION_REMOVE,
                'r',
                null,
                'Remove documents before indexing'
            )
            ->addOption(
                self::OPTION_TIMEOUT,
                't',
                InputOption::VALUE_REQUIRED,
                'Timeout for extraction in seconds'
            )
            ->setAliases(['index']);
    }

    protected function processArguments(InputInterface $input)
    {
        parent::processArguments($input);

        $blockSize = $input->getOption(self::OPTION_BLOCKSIZE);

        $blockSize = ltrim($blockSize, '=');

        if ($blockSize !== null && (! ctype_digit($blockSize) || ! $blockSize > 0)) {
            throw new InvalidOptionException('Blocksize must be an integer >= 1');
        } else {
            $this->blockSize = $blockSize;
        }
    }

    /**
     * @return int
     * @throws ModelException
     * @throws Zend_Config_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $clearCache = $input->getOption(self::OPTION_CLEAR_CACHE);
        $remove     = $input->getOption(self::OPTION_REMOVE);
        $timeout    = $input->getOption(self::OPTION_TIMEOUT);

        $startId = $this->startId;
        $endId   = $this->endId;

        $builder = new IndexHelper();
        $builder->setOutput($output);
        $builder->setBlockSize($this->blockSize);
        $builder->setClearCache($clearCache);
        $builder->setRemoveBeforeIndexing($remove);
        $builder->setTimeout($timeout);

        try {
            if ($this->isSingleDocument()) {
                $runtime = $builder->index($startId);
            } else {
                $runtime = $builder->index($startId, $endId);
            }
            $message = sprintf('Operation completed successfully in <fg=yellow>%.2f</> seconds.', $runtime);
            $output->writeln($message);
        } catch (SearchException $e) {
            $output->writeln('An error occurred while indexing.');
            $output->writeln('Error Message: ' . $e->getMessage());
            if ($e->getPrevious() !== null) {
                $output->writeln('Caused By: ' . $e->getPrevious()->getMessage());
            }
            $output->writeln('Stack Trace:');
            $output->writeln($e->getTraceAsString());
            $output->writeln('');
        }

        return 0;
    }
}
