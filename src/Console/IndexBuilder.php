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
 * @category    Application
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2010-2020, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Console;

use Opus\Search\Exception;
use Opus\Search\Service;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Indexes all or a range of documents.
 *
 * If all documents are indexed the index is cleared first.
 */
class IndexBuilder
{

    /**
     * Temporary variable for storing sync mode.
     * @var bool
     */
    private $syncMode = true;

    protected $docMaxDigits;

    /**
     * @var OutputInterface
     */
    private $output;

    private $blockSize = 10;

    private $clearCache = false;

    private $removeBeforeIndexing = false;

    /**
     * @param $startId
     * @param $endId
     * @return float|string
     * @throws Exception
     * @throws \Opus\Model\Exception
     * @throws \Zend_Config_Exception
     *
     * TODO Is the timestamp in the console output useful?
     */
    public function index($startId, $endId = -1)
    {
        $output = $this->getOutput();
        $blockSize = $this->getBlockSize();

        $this->forceSyncMode();

        $removeAll = false;

        // TODO this is a hack to detect if $endId has not been specified - better way?
        if ($endId === -1) {
            $singleDocument = true;
        } else {
            $singleDocument = false;
            if ($startId === null && $endId === null) {
                $removeAll = true;
            }
        }

        if ($singleDocument) {
            $docIds = [$startId];
        } else {
            $docIds = $this->getDocumentIds($startId, $endId);
        }

        $docCount = count($docIds);
        $this->docMaxDigits = strlen(( string )$docCount);

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            if (! $singleDocument) {
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

        if ($this->getRemoveBeforeIndexing()) {
            if ($singleDocument) {
                $output->writeln("Removing document <fg=yellow>$startId</> from index ... ");
                $indexer->removeDocumentsFromIndexById($docIds);
            } elseif ($removeAll) {
                $output->writeln('Removing <fg=yellow>all</> documents from index ... ');
                $indexer->removeAllDocumentsFromIndex();
            } else {
                $output->writeln("Removing <fg=yellow>$docCount</> documents from index ... ");
                $indexer->removeDocumentsFromIndexById($docIds);
            }
        }

        if ($singleDocument) {
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

        $clearCache = $this->getClearCache();

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
    private function outputProgress($runtime, $numOfDocs)
    {
        $output = $this->getOutput();

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

    private function addDocumentsToIndex($indexer, $docs)
    {
        $output = $this->getOutput();

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

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function getOutput()
    {
        if ($this->output === null) {
            $this->output = new NullOutput();
        }

        return $this->output;
    }

    /**
     * Returns IDs for published documents in range.
     *
     * @param $start int Start of ID range
     * @param $end int End of ID range
     * @return array Array of document IDs
     *
     * TODO exists here and in AbstractIndexCommand
     */
    public function getDocumentIds($start, $end)
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

    public function setBlockSize($blockSize)
    {
        $this->blockSize = $blockSize;
    }

    public function getBlockSize()
    {
        return $this->blockSize;
    }

    public function setClearCache($clearCache)
    {
        $this->clearCache = $clearCache;
    }

    public function getClearCache()
    {
        return $this->clearCache;
    }

    public function setRemoveBeforeIndexing($remove)
    {
        $this->removeBeforeIndexing = $remove;
    }

    public function getRemoveBeforeIndexing()
    {
        return $this->removeBeforeIndexing;
    }
}
