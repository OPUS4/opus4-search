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
 * @category    Application
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2020, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Console;

use Opus\Console\BaseDocumentCommand;
use Opus\Search\Service;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends BaseDocumentCommand
{

    protected static $defaultName = 'index:remove';

    protected $startIdDescription = 'ID of document where removing should start (or \'-\')';

    protected $endIdDescription = 'ID of document where removing should stop (or \'-\')';

    /**
     */
    protected function configure()
    {
        parent::configure();

        $help = <<<EOT
The <fg=green>index:remove</> (short <fg=green>i:r</>) command can be used to remove documents from the
index. It is possible to remove all or a range of documents. A single document 
can be removed by specifying its ID. 

Examples:
  <fg=yellow></>        will remove all documents 
  <fg=yellow>50</>      will remove document 50
  <fg=yellow>20 60</>   will remove documents 20 to 60
  <fg=yellow>20 -</>    will remove all documents starting from 20
  <fg=yellow>- 50</>    will remove all documents up to 50
  
The command does not check if the documents are present in the index before 
removing them. 
There will be an error message when trying to remove a single document <fg=green>ID</> 
that does not exist at all.
EOT;

        $this->setName('index:remove')
            ->setDescription('Removes documents from search index')
            ->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $startId = $this->startId;
        $endId = $this->endId;

        $indexer = Service::selectIndexingService('indexBuilder');

        if ($this->isAllDocuments()) {
            $output->write('Removing all documents from index ... ');
            $indexer->removeAllDocumentsFromIndex();
            $output->writeln('done');
        } else {
            if ($this->isSingleDocument()) {
                $doc = new \Opus_Document($startId);
                $output->write("Removing document $startId from index ... ");
                $indexer->removeDocumentsFromIndexById([$startId]);
                $output->writeln('done');
            } else {
                $helper = new DocumentHelper();
                $documents = $helper->getDocumentIds($startId, $endId);
                $docCount = count($documents);
                $output->write("Removing $docCount documents from index ... ");
                $indexer->removeDocumentsFromIndexById($documents);
                $output->writeln('done');
            }
        }
    }
}
