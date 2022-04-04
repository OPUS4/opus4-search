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
 * @copyright   Copyright (c) 2008-2022, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Util;

use Opus\Common\Log;
use Opus\Common\Repository;
use Opus\Document;
use Opus\Model\NotFoundException;
use Opus\Search\Exception;
use Opus\Search\QueryFactory;
use Opus\Search\Service;
use Zend_Config_Exception;
use Zend_Exception;

use function count;
use function microtime;

class ConsistencyCheck
{
    private $logger;

    private $searcher;

    private $indexer;

    private $numOfInconsistencies = 0;

    private $numOfUpdates = 0;

    private $numOfDeletions = 0;

    /**
     * @param Log|null $logger
     * @throws Zend_Config_Exception
     * @throws Zend_Exception
     */
    public function __construct($logger = null)
    {
        $this->logger   = $logger ?? Log::get();
        $this->searcher = Service::selectSearchingService();
        $this->indexer  = Service::selectIndexingService();
    }

    public function run()
    {
        $runtime = microtime(true);

        $this->checkDatabase();
        $this->checkSearchIndex();

        if ($this->numOfUpdates > 0) {
            $resolvedInconsistencies = $this->numOfUpdates + $this->numOfDeletions;
            $this->logger->info("$this->numOfInconsistencies inconsistencies were detected: $resolvedInconsistencies of them were resolved.");
            $this->logger->info("number of updates: $this->numOfUpdates");
            $this->logger->info("number of deletions: $this->numOfDeletions");
            $numOfErrors = $this->numOfInconsistencies - $resolvedInconsistencies;
            if ($numOfErrors > 0) {
                $this->logger->err("$numOfErrors error(s) occurred -- check log messages above for more details.");
            }
        } else {
            $this->logger->info("No inconsistency was detected.");
        }

        $runtime = microtime(true) - $runtime;
        $this->logger->info("Completed operation after $runtime seconds.");
    }

    /**
     * Check for each database document in serverState publish if it exists in
     * Solr index. Furthermore, compare field value of serverDateModified in
     * database and Solr index.
     */
    private function checkDatabase()
    {
        $finder = Repository::getInstance()->getDocumentFinder();
        $finder->setServerState('published');
        $ids = $finder->getIds();

        $this->logger->info('checking ' . count($ids) . ' published documents for consistency.');

        foreach ($ids as $id) {
            try {
                $doc = Document::get($id);
            } catch (NotFoundException $e) {
                // ignore: document was deleted from database in meantime
                continue;
            }

            $serverDataModified = $doc->getServerDateModified()->getUnixTimestamp();

            // retrieve document from index and compare serverDateModified fields
            $query  = QueryFactory::selectDocumentById($this->searcher, $id);
            $result = $this->searcher->customSearch($query);

            switch ($result->getAllMatchesCount()) {
                case 0:
                    $this->logger->info("inconsistency found for document $id: document is in database, but is not in Solr index.");
                    $this->numOfInconsistencies++;
                    if ($this->forceReindexing($doc)) {
                        $this->numOfUpdates++;
                    }
                    break;

                case 1:
                    if ($result->getReturnedMatches()[0]->getServerDateModified()->getUnixTimestamp() !== $serverDataModified) {
                        $this->numOfInconsistencies++;
                        $this->logger->info("inconsistency found for document $id: mismatch between values of server_date_modified in database and Solr index.");
                        if ($this->forceReindexing($doc)) {
                            $this->numOfUpdates++;
                        }
                    }
                    break;

                default:
                    $this->logger->err('unexpected state: document with id ' . $id . ' exists multiple times in index.');
            }
        }
    }

    /**
     * Find documents in Solr index, that are not in database or that are in
     * database but not in serverState published Remove such documents from Solr
     * index.
     */
    private function checkSearchIndex()
    {
        $query  = QueryFactory::selectAllDocuments($this->searcher);
        $result = $this->searcher->customSearch($query);

        $results = $result->getReturnedMatchingIds();
        foreach ($results as $id) {
            try {
                $doc = Document::get($id);
            } catch (NotFoundException $e) {
                $this->logger->info("inconsistency found for document $id: document is in Solr index, but is not in database.");
                $this->numOfInconsistencies++;
                if ($this->removeDocumentFromSearchIndex($id)) {
                    $this->numOfDeletions++;
                }
                continue;
            }
            if ($doc->getServerState() !== 'published') {
                $this->logger->info("inconsistency found for document $id: document is in Solr index, but is not in ServerState published.");
                $this->numOfInconsistencies++;
                if ($this->removeDocumentFromSearchIndex($id)) {
                    $this->numOfDeletions++;
                }
            }
        }
    }

    /**
     * Forces the reindexing of the given document.
     *
     * @param Document $doc
     * @return bool Returns true, iff the given document was successfully updated in Solr index.
     */
    private function forceReindexing($doc)
    {
        try {
            $doc->unregisterPlugin('Opus_Document_Plugin_Index'); // prevent document from being indexed twice
            $this->indexer->addDocumentsToIndex($doc);
        } catch (Exception $e) {
            $this->logger->err('Could not force reindexing of document ' . $doc->getId() . ' : ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Removes a document from Solr index.
     *
     * @param int $id Document ID
     * @return bool Returns true, iff the given document was successfully deleted from Solr index.
     */
    private function removeDocumentFromSearchIndex($id)
    {
        try {
            $this->indexer->removeDocumentsFromIndexById($id);
        } catch (Exception $e) {
            $this->logger->err('Could not delete document ' . $id . ' from index : ' . $e->getMessage());
            return false;
        }
        return true;
    }
}
