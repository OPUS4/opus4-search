<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
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
use Opus\Search\Console\Helper\IndexHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TODO extract single file
 * TODO use some kind of helper instead of base class?
 * TODO need to have a way to access/check cache (perhaps another command)
 */
class ExtractCommand extends AbstractBaseDocumentCommand
{
    const OPTION_TIMEOUT = 'timeout';

    /** @var string */
    protected static $defaultName = 'index:extract';

    /** @var string */
    protected $startIdDescription = 'ID of document where extraction should start (or \'-\')';

    /** @var string */
    protected $endIdDescription = 'ID of document where extraction should stop (or \'-\')';

    protected function configure()
    {
        parent::configure();

        $help = <<<EOT
The <fg=green>index:extract</> command can be used to build up the full text cache for 
documents. This is useful for testing full text extraction and speeding up 
subsequent indexing runs.

Using <fg=green>StartID</> and <fg=green>EndID</> a range of documents can be specified for the full 
text extraction.

You can use a dash (<fg=yellow>-</>) as <fg=green>StartID</> or <fg=green>EndID</>, if you want to extract all 
documents up to or starting from an ID. 

Examples:
  <fg=yellow></>        will extract full texts for all documents 
  <fg=yellow>50</>      will extract full texts for document 50
  <fg=yellow>20 60</>   will extract full texts for documents 20 to 60
  <fg=yellow>20 -</>    will extract full texts for documents starting from 20
  <fg=yellow>- 50</>    will extract full texts for documents up to 50
EOT;

        $this->setName(static::$defaultName)
            ->setDescription('Extracts text from document files for indexing')
            ->setHelp($help)
            ->addOption(
                self::OPTION_TIMEOUT,
                't',
                InputOption::VALUE_REQUIRED,
                'Timeout for extraction in seconds'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $timeout = $input->getOption(self::OPTION_TIMEOUT);

        $helper = new IndexHelper();
        $helper->setOutput($output);

        if ($timeout !== null) {
            $helper->setTimeout($timeout);
        }

        if ($this->isSingleDocument()) {
            $helper->extract($this->startId);
        } else {
            $helper->extract($this->startId, $this->endId);
        }
    }
}
