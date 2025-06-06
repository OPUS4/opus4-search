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
 * @copyright   Copyright (c) 2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Console\Helper;

use Opus\Common\Collection;
use Opus\Common\Config;
use Opus\Common\Console\Helper\ProgressBar;
use Opus\Common\Console\Helper\ProgressMatrix;
use Opus\Common\Console\Helper\ProgressOutput;
use Opus\Common\Console\Helper\ProgressReport;
use Opus\Common\Document;
use Opus\Common\DocumentInterface;
use Opus\Common\Model\ModelException;
use Opus\Common\Model\Xml\XmlCacheInterface;
use Opus\Common\Repository;
use Opus\Common\Storage\StorageException;
use Opus\Search\IndexingInterface;
use Opus\Search\MimeTypeNotSupportedException;
use Opus\Search\Plugin\Index;
use Opus\Search\SearchException;
use Opus\Search\Service;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Config_Exception;

use function count;
use function date;
use function filter_var;
use function max;
use function memory_get_peak_usage;
use function microtime;
use function min;
use function sprintf;

use const FILTER_VALIDATE_BOOLEAN;
use const PHP_EOL;

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
     *
     * @var bool
     */
    private $syncMode = true;

    /** @var OutputInterface */
    private $output;

    /** @var int */
    private $blockSize = 10;

    /** @var XmlCacheInterface */
    private $cache;

    /** @var bool */
    private $clearCache = false;

    /** @var bool */
    private $removeBeforeIndexing = false;

    /** @var int */
    private $timeout;

    /**
     * @return float|string
     */
    public function indexAll()
    {
        return $this->index(null, null);
    }

    /**
     * @param int $startId
     * @param int $endId
     * @param int $colId
     * @return float|string
     * @throws SearchException
     * @throws ModelException
     * @throws Zend_Config_Exception
     *
     * TODO Is the timestamp in the console output useful?
     */
    public function index($startId, $endId = -1, $colId = 0)
    {
        $output    = $this->getOutput();
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
            $docIds = $documentHelper->getDocumentIds($startId, $endId, $colId);
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

        $timeout = $this->getTimeout();

        if ($timeout !== null) {
            $indexer->setTimeout($timeout);
        }

        if ($this->getRemoveBeforeIndexing()) {
            if ($singleDocument) {
                $output->writeln("Removing document <fg=yellow>$startId</> from index ... ");
                $indexer->removeDocumentsFromIndexById($docIds);
            } elseif ($removeAll && $colId === 0) {
                $output->writeln('Removing <fg=yellow>all</> documents from index ... ');
                $indexer->removeAllDocumentsFromIndex();
            } else {
                $output->writeln("Removing <fg=yellow>$docCount</> documents from index ... ");
                $indexer->removeDocumentsFromIndexById($docIds);
            }
        }

        if ($singleDocument) {
            $output->writeln("Indexing document <fg=yellow>$startId</> ...");
        } elseif ($colId > 0) {
            $col      = Collection::get($colId);
            $colTitle = $col->getDisplayName();
            $output->writeln("Indexing documents in collection: \"${colTitle}\" (ID=$colId)");
        } elseif ($endId !== 0) {
            $output->writeln("Indexing document from <fg=yellow>$startId</> to <fg=yellow>$endId</> ...");
        } elseif ($startId !== 0) {
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

            case $output::VERBOSITY_VERY_VERBOSE:
            case $output::VERBOSITY_DEBUG:
                $progress = new ProgressMatrix($output, $docCount);
                break;

            default:
                $progress = new ProgressBar($output, $docCount);
                break;
        }

        $report = new ProgressReport();

        $progress->start();

        $docs = [];

        // measure time for each document
        // TODO removed timing of single document indexing (detect long indexing processes) - add again?

        foreach ($docIds as $docId) {
            $doc = $this->getDocument($docId);

            $docs[] = $doc;

            $numOfDocs++;

            if ($numOfDocs % $blockSize === 0) {
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

    /**
     * @param int $startId
     * @param int $endId
     * @return float|string
     * @throws ModelException
     * @throws Zend_Config_Exception
     *
     * TODO perhaps support different output formats (like XML for automated processing)
     * TODO show documents without files
     * TODO step = file instead of step = document = n-files (requires SQL query to get all linked files)
     */
    public function extract($startId, $endId = -1)
    {
        $output = $this->getOutput();

        $this->forceSyncMode();

        $documentHelper = new DocumentHelper();

        // TODO this is a hack to detect if $endId has not been specified - better way?
        if ($endId <= 0) {
            $singleDocument = true;
            $docIds         = [$startId];
        } else {
            $singleDocument = false;
            if ($startId === 0 && $endId === 0) {
                $removeAll = true;
            }
            $docIds = $documentHelper->getDocumentIds($startId, $endId);
        }

        $extractor = Service::selectExtractingService('indexBuilder');

        $timeout = $this->getTimeout();

        if ($timeout !== null) {
            $extractor->setTimeout($timeout);
        }

        $docCount = count($docIds);

        if ($singleDocument) {
            $output->writeln("Start extracting text from files for document <fg=yellow>$startId</>.");
        } else {
            $output->writeln("Start extracting text from files for <fg=yellow>$docCount</> documents.");
        }
        $output->writeln('');

        $numOfDocs = 0;
        $runtime   = microtime(true);

        $report = new ProgressReport();

        $progress = new ProgressMatrix($output, $docCount);
        $progress->start();

        // measure time for each document

        foreach ($docIds as $docId) {
            $status = null;

            $timeStart = microtime(true);

            $doc = Document::get($docId);

            $files = $doc->getFile();

            if (count($files) > 0) {
                foreach ($files as $file) {
                    try {
                        $extractor->extractDocumentFile($file, $doc);
                    } catch (MimeTypeNotSupportedException $e) {
                        // TODO depending on verbosity show a message for this
                        // TODO don't overwrite higher status like 'F'
                        if ($output->isVerbose()) {
                            if ($status === null) {
                                $status = '<fg=yellow>S</>';
                            }
                            $report->addException($e);
                        }
                    } catch (StorageException $e) {
                        $report->addException($e);
                        $status = '<fg=red>E</>';
                    } catch (SearchException $e) {
                        $report->addException($e);
                        $status = '<fg=red>E</>';
                    }
                }
            }
            // TODO output doc without files message (only at highest verbosity level)

            $timeDelta = microtime(true) - $timeStart;
            if ($timeDelta > 30) {
                $output->writeln(date('Y-m-d H:i:s') . " WARNING: Extracting document $docId took $timeDelta seconds.");
            }

            $numOfDocs++;
            $progress->advance(1, $status);

            if ($status !== null) {
                $report->setEntryInfo("Document <fg=yellow>$docId</>");
            }

            $report->finishEntry();
        }

        $progress->finish();

        $runtime    = microtime(true) - $runtime;
        $peakMemory = memory_get_peak_usage() / 1024 / 1024;

        // TODO handle longer runtimes (minutes, hours)
        $output->writeln('');
        $message = sprintf('Time: <fg=yellow>%.2f</> seconds, Memory: %.2f MB', $runtime, $peakMemory);
        $output->writeln($message);

        // new search API doesn't track number of indexed files, but issues are kept written to log file
        //echo "\n\nErrors appeared in " . $indexer->getErrorFileCount() . " of " . $indexer->getTotalFileCount()
        //    . " files. Details were written to opus-console.log";

        $output->writeln(PHP_EOL . 'Details were written to <fg=green>opus-console.log</>');

        $report->write($output);

        $this->resetMode();

        return $runtime;
    }

    /**
     * @param IndexingInterface $indexer
     * @param array             $docs
     * @throws SearchException
     */
    private function addDocumentsToIndex($indexer, $docs)
    {
        $output = $this->getOutput();

        try {
            $indexer->addDocumentsToIndex($docs);
        } catch (SearchException $e) {
            // echo date('Y-m-d H:i:s') . " ERROR: Failed indexing document $docId.\n";
            $output->writeln(date('Y-m-d H:i:s') . "        {$e->getMessage()}");
        } catch (StorageException $e) {
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
        $config = Config::get();
        if (isset($config->runjobs->asynchronous) && filter_var($config->runjobs->asynchronous, FILTER_VALIDATE_BOOLEAN)) {
            $this->syncMode                = false;
            $config->runjobs->asynchronous = ''; // false
        }
    }

    private function resetMode()
    {
        if (! $this->syncMode) {
            $config                        = Config::get();
            $config->runjobs->asynchronous = '1'; // true
        }
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        if ($this->output === null) {
            $this->output = new NullOutput();
        }

        return $this->output;
    }

    /**
     * @param int $docId
     * @return DocumentInterface
     */
    protected function getDocument($docId)
    {
        if ($this->getClearCache()) {
            $cache = $this->getCache();
            $cache->remove($docId);
        }

        $doc = Document::get($docId);

        // TODO dirty hack: disable implicit reindexing of documents in case of cache misses
        $doc->unregisterPlugin(Index::class);

        return $doc;
    }

    /**
     * @param int $blockSize
     */
    public function setBlockSize($blockSize)
    {
        $this->blockSize = $blockSize;
    }

    /**
     * @return int
     */
    public function getBlockSize()
    {
        return $this->blockSize;
    }

    /**
     * @param bool $clearCache
     */
    public function setClearCache($clearCache)
    {
        $this->clearCache = $clearCache;
    }

    /**
     * @return bool
     */
    public function getClearCache()
    {
        return $this->clearCache;
    }

    /**
     * @param bool $remove
     */
    public function setRemoveBeforeIndexing($remove)
    {
        $this->removeBeforeIndexing = $remove;
    }

    /**
     * @return bool
     */
    public function getRemoveBeforeIndexing()
    {
        return $this->removeBeforeIndexing;
    }

    /**
     * @return XmlCacheInterface
     */
    public function getCache()
    {
        if ($this->cache === null) {
            $this->cache = Repository::getInstance()->getDocumentXmLCache();
        }

        return $this->cache;
    }

    /**
     * @param XmlCacheInterface $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
}
