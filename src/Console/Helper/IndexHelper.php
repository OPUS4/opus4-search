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

namespace Opus\Search\Console\Helper;

use Opus\Console\Helper\ProgressBar;
use Opus\Console\Helper\ProgressOutput;
use Opus\Search\Exception;
use Opus\Search\Indexing;
use Opus\Search\Service;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Indexes all or a range of documents.
 *
 * If all documents are indexed the index is cleared first.
 *
 * TODO cleanup and document
 */
class IndexHelper
{

    /**
     * Temporary variable for storing sync mode.
     * @var bool
     */
    private $syncMode = true;

    /**
     * @var OutputInterface
     */
    private $output;

    private $blockSize = 10;

    private $cache;

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

        $documentHelper = new DocumentHelper();

        if ($singleDocument) {
            $docIds = [$startId];
        } else {
            $docIds = $documentHelper->getDocumentIds($startId, $endId);
        }

        $docCount = count($docIds);

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

        switch ($output->getVerbosity()) {
            case $output::VERBOSITY_VERBOSE:
                $progress = new ProgressOutput($output, $docCount);
                break;
            default:
                $progress = new ProgressBar($output, $docCount);
                break;
        }
        $progress->start();

        $docs = [];

        // measure time for each document
        // TODO removed timing of single document indexing (detect long indexing processes) - add again?

        foreach ($docIds as $docId) {
            $doc = $this->getDocument($docId);

            $docs[] = $doc;

            $numOfDocs++;

            if ($numOfDocs % $blockSize == 0) {
                $this->addDocumentsToIndex($indexer, $docs);
                $docs = [];
                $progress->setProgress($numOfDocs);
            }
        }

        // Index leftover documents
        if (count($docs) > 0) {
            $this->addDocumentsToIndex($indexer, $docs);
            $docs = [];
            $progress->setProgress($numOfDocs);
        }

        $progress->finish();

        $output->writeln(date('Y-m-d H:i:s') . ' Finished indexing.');
        // new search API doesn't track number of indexed files, but issues are being written to log file
        //echo "\n\nErrors appeared in " . $indexer->getErrorFileCount() . " of " . $indexer->getTotalFileCount()
        //    . " files. Details were written to opus-console.log";
        $output->writeln('Details were written to <fg=green>opus-console.log</>');

        $this->resetMode();

        return $progress->getRuntime();
    }

    public function extract($startId, $endId)
    {
        $output = $this->getOutput();

        $this->forceSyncMode();

        $docIds = $this->getDocumentIds($startId, $endId);

        $extractor = Service::selectIndexingService('indexBuilder');

        $docCount = count($docIds);

        $output->writeln(date('Y-m-d H:i:s') . " Start indexing of <fg=yellow>$docCount</> documents.");
        $numOfDocs = 0;
        $runtime = microtime(true);

        $progress = new ProgressBar($output, $docCount);
        $progress->start();

        // measure time for each document

        foreach ($docIds as $docId) {
            $timeStart = microtime(true);

            $doc = new \Opus_Document($docId);

            foreach ($doc->getFile() as $file) {
                try {
                    $extractor->extractDocumentFile($file, $doc);
                } catch (Exception $e) {
                    $output->writeln(date('Y-m-d H:i:s') . " ERROR: Failed extracting document $docId.");
                    $output->writeln(date('Y-m-d H:i:s') . "        {$e->getMessage()}");
                } catch (\Opus_Storage_Exception $e) {
                    $output->writeln(date('Y-m-d H:i:s') . " ERROR: Failed extracting unavailable file on document $docId.");
                    $output->writeln(date('Y-m-d H:i:s') . "        {$e->getMessage()}");
                }
            }

            $timeDelta = microtime(true) - $timeStart;
            if ($timeDelta > 30) {
                $output->writeln(date('Y-m-d H:i:s') . " WARNING: Extracting document $docId took $timeDelta seconds.");
            }

            $numOfDocs++;
            $progress->advance();

            if ($numOfDocs % 10 == 0) {
                // TODO $this->outputProgress($runtime, $numOfDocs);
            }
        }

        $progress->finish();

        $runtime = microtime(true) - $runtime;
        $output->writeln(date('Y-m-d H:i:s') . ' Finished extracting.');
        // new search API doesn't track number of indexed files, but issues are kept written to log file
        //echo "\n\nErrors appeared in " . $indexer->getErrorFileCount() . " of " . $indexer->getTotalFileCount()
        //    . " files. Details were written to opus-console.log";
        $output->writeln('Details were written to <fg=green>opus-console.log</>');

        $this->resetMode();

        return $runtime;
    }

    private function addDocumentsToIndex(Indexing $indexer, $docs)
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
     * TODO not IndexHelper specific functionality
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

    protected function getDocument($docId)
    {
        if ($this->getClearCache()) {
            $cache = $this->getCache();
            $cache->remove($docId);
        }

        $doc = new \Opus_Document($docId);

        // TODO dirty hack: disable implicit reindexing of documents in case of cache misses
        $doc->unregisterPlugin('Opus\Search\Plugin\Index');

        return $doc;
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

    public function getCache()
    {
        if ($this->cache === null) {
            $this->cache = new \Opus_Model_Xml_Cache();
        }

        return $this->cache;
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }
}
