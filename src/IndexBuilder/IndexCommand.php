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

namespace Opus\Search\IndexBuilder;

use Opus\Search\Exception;
use Opus\Search\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IndexCommand
 * @package Opus\Search\IndexBuilder
 *
 * TODO move indexing code into different class
 */
class IndexCommand extends Command
{

    const OPTION_BLOCKSIZE = 'blocksize';

    const OPTION_CLEAR_CACHE = 'clear-cache';

    const OPTION_OPTIMIZE = 'optimize';

    const ARGUMENT_START_ID = 'StartID';

    const ARGUMENT_END_ID = 'EndID';

    protected static $defaultName = 'index';

    /**
     * Temporary variable for storing sync mode.
     * @var bool
     */
    private $syncMode = true;

    /**
     */
    protected function configure()
    {
        $help = 'If only StartID is specified all remaining documents with higher IDs will be indexed.' . PHP_EOL;
        $help .= 'If no ID is specified the entire index will be cleared before reindexing all documents.';

        $this->setName('index')
            ->setDescription('Indexes documents')
            ->setHelp($help)
            ->addOption(
                self::OPTION_BLOCKSIZE,
                'b',
                InputOption::VALUE_REQUIRED,
                'Max number of documents indexed together'
            )
            ->addOption(
                self::OPTION_OPTIMIZE,
                'o',
                null,
                'Optimize index after indexing'
            )
            ->addOption(
                self::OPTION_CLEAR_CACHE,
                'c',
                null,
                'Clear document XML cache entries before indexing'
            )
            ->addArgument(
                'StartID',
                InputArgument::OPTIONAL,
                'ID of document where indexing should start'
            )
            ->addArgument(
                'EndID',
                InputArgument::OPTIONAL,
                'ID of document where indexing should stop'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $optimize = $input->getOption(self::OPTION_OPTIMIZE);
        $clearCache = $input->getOption(self::OPTION_CLEAR_CACHE);
        $blockSize = $input->getOption(self::OPTION_BLOCKSIZE);
        $startId = $input->getArgument(self::ARGUMENT_START_ID);
        $endId = $input->getArgument(self::ARGUMENT_END_ID);

        if ($startId !== null && ! ctype_digit($startId)) {
            throw new InvalidArgumentException('StartID needs to be an integer.');
        }

        if ($endId !== null && ! ctype_digit($endId)) {
            throw new InvalidArgumentException('EndID needs to be an integer.');
        }

        if ($startId === null && $endId === null) {
            $removeAll = true;
        } else {
            $removeAll = false;
            if ($startId > $endId) {
                $tmp = $startId;
                $startId = $endId;
                $endId = $tmp;
            }
        }

        if (! is_null($endId)) {
            $output->writeln("Indexing documents {$startId} to {$endId} ...");
        } elseif (! is_null($startId)) {
            $output->writeln("Indexing documents starting at ID = {$startId} ...");
        } else {
            $output->writeln('Indexing all documents ...');
        }

        try {
            $runtime = $this->index($startId, $endId, $removeAll, $clearCache);
            $output->writeln("Operation completed successfully in $runtime seconds.");
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

    private function index($startId, $endId, $removeAll = false, $clearCache = false)
    {
        $this->forceSyncMode();

        $docIds = $this->getDocumentIds($startId, $endId);

        $indexer = Service::selectIndexingService('indexBuilder');

        if ($removeAll) {
            echo 'Removing all documents from the index ...' . PHP_EOL;
            $indexer->removeAllDocumentsFromIndex();
        }

        echo date('Y-m-d H:i:s') . " Start indexing of " . count($docIds) . " documents.\n";
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

            // dirty hack: disable implicit reindexing of documents in case of cache misses
            $doc->unregisterPlugin('Opus\Search\Plugin\Index');

            $docs[] = $doc;

            $timeDelta = microtime(true) - $timeStart;
            if ($timeDelta > 30) {
                echo date('Y-m-d H:i:s') . " WARNING: Indexing document $docId took $timeDelta seconds.\n";
            }

            $numOfDocs++;

            if ($numOfDocs % 10 == 0) {
                $this->addDocumentsToIndex($indexer, $docs);
                $docs = [];
                $this->outputProgress($runtime, $numOfDocs);
            }
        }

        // Index leftover documents
        if (count($docs) > 0) {
            $this->addDocumentsToIndex($indexer, $docs);
            $this->outputProgress($runtime, $numOfDocs);
        }

        $runtime = microtime(true) - $runtime;
        echo PHP_EOL . date('Y-m-d H:i:s') . ' Finished indexing.' . PHP_EOL;
        // new search API doesn't track number of indexed files, but issues are being written to log file
        //echo "\n\nErrors appeared in " . $indexer->getErrorFileCount() . " of " . $indexer->getTotalFileCount()
        //    . " files. Details were written to opus-console.log";
        echo PHP_EOL . 'Details were written to opus-console.log';

        $this->resetMode();

        return $runtime;
    }

    /**
     * Returns IDs for published documents in range.
     *
     * @param $start int Start of ID range
     * @param $end int End of ID range
     * @return array Array of document IDs
     */
    private function getDocumentIds($start, $end)
    {
        $finder = new \Opus_DocumentFinder();

        if (isset($start)) {
            $finder->setIdRangeStart($start);
        }

        if (isset($end)) {
            $finder->setIdRangeEnd($end);
        }

        return $finder->ids();
    }

    /**
     * Output current processing status and performance.
     *
     * @param $runtime long Time of start of processing
     * @param $numOfDocs Number of processed documents
     */
    private function outputProgress($runtime, $numOfDocs)
    {
        $memNow = round(memory_get_usage() / 1024 / 1024);
        $memPeak = round(memory_get_peak_usage() / 1024 / 1024);

        $deltaTime = microtime(true) - $runtime;
        $docPerSecond = round($deltaTime) == 0 ? 'inf' : round($numOfDocs / $deltaTime, 2);
        $secondsPerDoc = round($deltaTime / $numOfDocs, 2);

        echo date('Y-m-d H:i:s') . " Stats after $numOfDocs documents -- memory $memNow MB,"
            . " peak memory $memPeak (MB), $docPerSecond docs/second, $secondsPerDoc seconds/doc" . PHP_EOL;
    }

    private function addDocumentsToIndex($indexer, $docs)
    {
        try {
            $indexer->addDocumentsToIndex($docs);
        } catch (Opus\Search\Exception $e) {
            // echo date('Y-m-d H:i:s') . " ERROR: Failed indexing document $docId.\n";
            echo date('Y-m-d H:i:s') . "        {$e->getMessage()}\n";
        } catch (\Opus_Storage_Exception $e) {
            // echo date('Y-m-d H:i:s') . " ERROR: Failed indexing unavailable file on document $docId.\n";
            echo date('Y-m-d H:i:s') . "        {$e->getMessage()}\n";
        }
    }

    /**
     * Find better way to enable/disable sync mode during indexing.
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
