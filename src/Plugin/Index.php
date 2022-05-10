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

namespace Opus\Search\Plugin;

use InvalidArgumentException;
use Opus\Common\Config;
use Opus\Common\Model\ModelInterface;
use Opus\Common\Model\Plugin\AbstractPlugin;
use Opus\Document;
use Opus\Job;
use Opus\Model\AbstractDb;
use Opus\Search\Log;
use Opus\Search\SearchException;
use Opus\Search\Service;
use Opus\Search\Task\IndexOpusDocument;

use function filter_var;

use const FILTER_VALIDATE_BOOLEAN;

/**
 * Plugin for updating the solr index triggered by document changes.
 *
 * @uses        AbstractPlugin
 */
class Index extends AbstractPlugin
{
    private $config;

    /**
     * @param Config|null $config
     */
    public function __construct($config = null)
    {
        $this->config = $config ?? Config::get();
    }

    /**
     * Post-store hook will be called right after the document has been stored
     * to the database.  If set to synchronous, update index.  Otherwise add
     * job to worker-queue.
     *
     * If document state is set to something != published, remove document.
     *
     * @see {\Opus_Model_Plugin_Interface::postStore}
     *
     * @param AbstractDb $model item written to store before
     */
    public function postStore(ModelInterface $model)
    {
        // only index Opus_Document instances
        if (false === $model instanceof Document) {
            return;
        }

        // Skip indexing if document has not been published yet.  First we need
        // to reload the document, just to make sure the object is new,
        // unmodified and clean...
        // TODO: Write unit test.
        $model = Document::get($model->getId());

        if ($model->getServerState() === 'temporary') {
            // TODO does this make sense here - do we need it?
            $this->removeDocumentFromIndexById($model->getId());
            return;
        }

        $this->addDocumentToIndex($model);
    }

        /**
         * Post-delete-hook for document class: Remove document from index.
         *
         * @see {Opus_Model_Plugin_Interface::postDelete}
         *
         * @param mixed $modelId ID of item deleted before
         */
    public function postDelete($modelId)
    {
        if (null !== $modelId) {
            $this->removeDocumentFromIndexById($modelId);
        }
    }

    /**
     * Helper method to remove document from index.
     *
     * @param int $documentId
     */
    private function removeDocumentFromIndexById($documentId)
    {
        $log = Log::get();

        if (isset($this->config->runjobs->asynchronous) && filter_var($this->config->runjobs->asynchronous, FILTER_VALIDATE_BOOLEAN)) {
            $log->debug(__METHOD__ . ': Adding remove-index job for document ' . $documentId . '.');

            $job = new Job();
            $job->setLabel(IndexOpusDocument::LABEL);
            $job->setData([
                'documentId' => $documentId,
                'task'       => 'remove',
            ]);

            // skip creating job if equal job already exists
            if (true === $job->isUniqueInQueue()) {
                $job->store();
            } else {
                $log->debug(__METHOD__ . ': remove-index job for document ' . $documentId . ' already exists!');
            }
        } else {
            $log->debug(__METHOD__ . ': Removing document ' . $documentId . ' from index.');
            try {
                Service::selectIndexingService('onDocumentChange')
                    ->removeDocumentsFromIndexById($documentId);
            } catch (SearchException $e) {
                $log->debug(__METHOD__ . ': Removing document-id ' . $documentId . ' from index failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Helper method to add document to index.
     */
    private function addDocumentToIndex(Document $document)
    {
        $documentId = $document->getId();

        $log = Log::get();

        // create job if asynchronous is set
        if (isset($this->config->runjobs->asynchronous) && filter_var($this->config->runjobs->asynchronous, FILTER_VALIDATE_BOOLEAN)) {
            $log->debug(__METHOD__ . ': Adding index job for document ' . $documentId . '.');

            $job = new Job();
            $job->setLabel(IndexOpusDocument::LABEL);
            $job->setData([
                'documentId' => $documentId,
                'task'       => 'index',
            ]);

            // skip creating job if equal job already exists
            if (true === $job->isUniqueInQueue()) {
                $job->store();
            } else {
                $log->debug(__METHOD__ . ': Indexing job for document ' . $documentId . ' already exists!');
            }
        } else {
            $log->debug(__METHOD__ . ': Index document ' . $documentId . '.');

            try {
                $service = Service::selectIndexingService('onDocumentChange');
                $service->addDocumentsToIndex($document);
            } catch (SearchException $e) {
                $log->debug(__METHOD__ . ': Indexing document ' . $documentId . ' failed: ' . $e->getMessage());
            } catch (InvalidArgumentException $e) {
                $log->warn(__METHOD__ . ': ' . $e->getMessage());
            }
        }
    }
}
