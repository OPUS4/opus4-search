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

use Opus\Search\Exception;
use Opus\Search\Service;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IndexCommand
 * @package Opus\Search\IndexBuilder
 *
 * TODO move indexing code into different class
 */
class IndexCommand extends AbstractIndexCommand
{

    const OPTION_BLOCKSIZE = 'blocksize';

    const OPTION_CLEAR_CACHE = 'clear-cache';

    const OPTION_REMOVE = 'remove';

    protected static $defaultName = 'index';

    /**
     * Temporary variable for storing sync mode.
     * @var bool
     */
    private $syncMode = true;

    protected $blockSize = 10;

    protected $docMaxDigits;

    /**
     */
    protected function configure()
    {
        parent::configure();

        $help = <<< EOT
The <fg=green>index:index</> (short <fg=green>i:i</>) command can be used to index a single document or a 
range of documents.

If no <fg=green>ID</> is provided, all documents will be indexed. Before the indexing starts, 
all documents will be removed from the search index.   

You can use a dash (<fg=yellow>-</>) as <fg=green>StartID</> or <fg=green>EndID</>, if you want to index all document up 
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $clearCache = $input->getOption(self::OPTION_CLEAR_CACHE);
        $remove = $input->getOption(self::OPTION_REMOVE);

        $startId = $this->startId;
        $endId = $this->endId;

        try {
            $runtime = $this->index($output, $startId, $endId, $remove, $clearCache);
            $message = sprintf('Operation completed successfully in <fg=yellow>%.2f</> seconds.', $runtime);
            $output->writeln($message);
        } catch (Exception $e) {
            $output->writeln('An error occurred while indexing.');
            $output->writeln('Error Message: ' . $e->getMessage());
            if ($e->getPrevious() !== null) {
                $output->writeln('Caused By: ' . $e->getPrevious()->getMessage());
            }
            $output->writeln('Stack Trace:');
            $output->writeln($e->getTraceAsString());
            $output->writeln();
        }
    }

    /**
     * @param $startId
     * @param $endId
     * @param false $removeAll
     * @param false $clearCache
     * @return float|string
     * @throws Exception
     * @throws \Opus\Model\Exception
     * @throws \Zend_Config_Exception
     *
     * TODO Is the timestamp in the console output useful?
     */
    private function index(OutputInterface $output, $startId, $endId, $remove = false, $clearCache = false)
    {
        $blockSize = $this->blockSize;

        $this->forceSyncMode();

        if ($this->singleDocument) {
            $docIds = [$startId];
        } else {
            $docIds = $this->getDocumentIds($startId, $endId);
        }

        $docCount = count($docIds);
        $this->docMaxDigits = strlen(( string )$docCount);

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            if (! $this->singleDocument) {
                $minId = min($docIds);
                $maxId = max($docIds);
                if ($docCount === 1) {
                    $output->writeln("Found <fg=yellow>1</> document (<fg=yellow>$minId</>)");
                } else {
                    $output->writeln("Found <fg=yellow>$docCount</> documents (<fg=yellow>$minId</> - <fg=yellow>$maxId</>)");
                }
            }
        }

        $indexer = Service::selectIndexingService('indexBuilder');

        if ($remove) {
            if ($this->singleDocument) {
                $output->writeln("Removing document <fg=yellow>$startId</> from index ... ");
                $indexer->removeDocumentsFromIndexById($docIds);
            } elseif ($this->removeAll) {
                $output->writeln('Removing <fg=yellow>all</> documents from index ... ');
                $indexer->removeAllDocumentsFromIndex();
            } else {
                $output->writeln("Removing <fg=yellow>$docCount</> documents from index ... ");
                $indexer->removeDocumentsFromIndexById($docIds);
            }
        }

        if ($this->singleDocument) {
            $output->writeln("Indexing document <fg=yellow>$startId</> ...");
        } elseif (! is_null($endId)) {
            $output->writeln("Indexing document from <fg=yellow>$startId</> to <fg=yellow>$endId</> ...");
        } elseif (! is_null($startId)) {
            $output->writeln("Indexing documents starting at <fg=yellow>$startId</> ...");
        } else {
            $output->writeln('Indexing <fg=yellow>all</> documents ...');
        }

        $output->writeln(date('Y-m-d H:i:s') . " Start indexing of <fg=yellow>$docCount</> documents ... ");
        $numOfDocs = 0;
        $runtime = microtime(true);

        $docs = [];

        // measure time for each document

        $cache = new \Opus_Model_Xml_Cache();

        foreach ($docIds as $docId) {
            $timeStart = microtime(true);

            if ($clearCache) {
                $cache->remove($docId);
            }

            $doc = new \Opus_Document($docId);

            // TODO dirty hack: disable implicit reindexing of documents in case of cache misses
            $doc->unregisterPlugin('Opus\Search\Plugin\Index');

            $docs[] = $doc;

            $timeDelta = microtime(true) - $timeStart;
            if ($timeDelta > 30) {
                // TODO does this still work
                $output->writeln(date('Y-m-d H:i:s') . " WARNING: Indexing document $docId took $timeDelta seconds.");
            }

            $numOfDocs++;

            if ($numOfDocs % $blockSize == 0) {
                $this->addDocumentsToIndex($output, $indexer, $docs);
                $docs = [];
                $this->outputProgress($output, $runtime, $numOfDocs);
            }
        }

        // Index leftover documents
        if (count($docs) > 0) {
            $this->addDocumentsToIndex($output, $indexer, $docs);
            $this->outputProgress($output, $runtime, $numOfDocs);
        }

        $runtime = microtime(true) - $runtime;
        $output->writeln(date('Y-m-d H:i:s') . ' Finished indexing.');
        // new search API doesn't track number of indexed files, but issues are being written to log file
        //echo "\n\nErrors appeared in " . $indexer->getErrorFileCount() . " of " . $indexer->getTotalFileCount()
        //    . " files. Details were written to opus-console.log";
        $output->writeln('Details were written to <fg=green>opus-console.log</>');

        $this->resetMode();

        return $runtime;
    }

    /**
     * Output current processing status and performance.
     *
     * @param $runtime long Time of start of processing
     * @param $numOfDocs Number of processed documents
     */
    private function outputProgress(OutputInterface $output, $runtime, $numOfDocs)
    {
        $memNow = round(memory_get_usage() / 1024 / 1024);
        $memPeak = round(memory_get_peak_usage() / 1024 / 1024);

        $deltaTime = microtime(true) - $runtime;
        $docPerSecond = round($deltaTime) == 0 ? 'inf' : round($numOfDocs / $deltaTime, 2);
        $secondsPerDoc = round($deltaTime / $numOfDocs, 2);

        $message = sprintf(
            "%s Stats after <fg=yellow>%{$this->docMaxDigits}d</> docs -- mem <fg=yellow>%3d</> MB, peak <fg=yellow>%3d</> MB, <fg=yellow>%6.2f</> docs/s, <fg=yellow>%5.2f</> s/doc",
            date('Y-m-d H:i:s'),
            $numOfDocs,
            $memNow,
            $memPeak,
            $docPerSecond,
            $secondsPerDoc
        );
        $output->writeln($message);
    }

    private function addDocumentsToIndex($output, $indexer, $docs)
    {
        try {
            $indexer->addDocumentsToIndex($docs);
        } catch (Opus\Search\Exception $e) {
            // echo date('Y-m-d H:i:s') . " ERROR: Failed indexing document $docId.\n";
            $output->writeln(date('Y-m-d H:i:s') . "        {$e->getMessage()}");
        } catch (\Opus_Storage_Exception $e) {
            // echo date('Y-m-d H:i:s') . " ERROR: Failed indexing unavailable file on document $docId.\n";
            $output->writeln(date('Y-m-d H:i:s') . "        {$e->getMessage()}");
        }
    }

    /**
     * TODO Find better way to enable/disable sync mode during indexing.
     */
    private function forceSyncMode()
    {
        $config = \Zend_Registry::get('Zend_Config');
        if (isset($config->runjobs->asynchronous) && filter_var($config->runjobs->asynchronous, FILTER_VALIDATE_BOOLEAN)) {
            $this->syncMode = false;
            $config->runjobs->asynchronous = ''; // false
            \Zend_Registry::set('Zend_Config', $config);
        }
    }

    private function resetMode()
    {
        if (! $this->syncMode) {
            $config = \Zend_Registry::get('Zend_Config');
            $config->runjobs->asynchronous = '1'; // true
            \Zend_Registry::set('Zend_Config', $config);
        }
    }
}
