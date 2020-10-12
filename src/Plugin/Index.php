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
 * @category    Framework
 * @author      Thoralf Klein <thoralf.klein@zib.de>
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2008-2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Plugin;

use Opus\Search\Exception;
use Opus\Search\Log;
use Opus\Search\Service;
use Opus\Search\Task\IndexOpusDocument;

/**
 * Plugin for updating the solr index triggered by document changes.
 *
 * @category    Framework
 * @package     Opus\Search\Plugin
 * @uses        \Opus\Model\Plugin\AbstractPlugin
 */
class Index extends \Opus\Model\Plugin\AbstractPlugin
{

    private $config;

    public function __construct($config = null)
    {
        $this->config = is_null($config) ? \Zend_Registry::get('Zend_Config') : $config;
    }

    /**
     * Post-store hook will be called right after the document has been stored
     * to the database.  If set to synchronous, update index.  Otherwise add
     * job to worker-queue.
     *
     * If document state is set to something != published, remove document.
     *
     * @param \Opus_Model_AbstractDb $model item written to store before
     * @see {\Opus_Model_Plugin_Interface::postStore}
     */
    public function postStore(\Opus\Model\ModelInterface $model)
    {
        // only index Opus_Document instances
        if (false === ($model instanceof \Opus_Document)) {
            return;
        }

        // Skip indexing if document has not been published yet.  First we need
        // to reload the document, just to make sure the object is new,
        // unmodified and clean...
        // TODO: Write unit test.
        $model = new \Opus_Document($model->getId());

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
         * @param mixed $modelId ID of item deleted before
         * @see {Opus_Model_Plugin_Interface::postDelete}
         */
    public function postDelete($modelId)
    {
        if (null === $modelId) {
            return;
        }

        return;
    }

    public function postDeletePermanent($modelId)
    {
        if (null === $modelId) {
            return;
        }

        $this->removeDocumentFromIndexById($modelId);
        return;
    }

    /**
     * Helper method to remove document from index.
     *
     * @param $documentId
     */
    private function removeDocumentFromIndexById($documentId)
    {
        $log = Log::get();

        if (isset($this->config->runjobs->asynchronous) && filter_var($this->config->runjobs->asynchronous, FILTER_VALIDATE_BOOLEAN)) {
            $log->debug(__METHOD__ . ': ' .'Adding remove-index job for document ' . $documentId . '.');

            $job = new \Opus_Job();
            $job->setLabel(IndexOpusDocument::LABEL);
            $job->setData([
                'documentId' => $documentId,
                'task' => 'remove'
            ]);

            // skip creating job if equal job already exists
            if (true === $job->isUniqueInQueue()) {
                $job->store();
            } else {
                $log->debug(__METHOD__ . ': ' . 'remove-index job for document ' . $documentId . ' already exists!');
            }
        } else {
            $log->debug(__METHOD__ . ': ' . 'Removing document ' . $documentId . ' from index.');
            try {
                Service::selectIndexingService('onDocumentChange')
                    ->removeDocumentsFromIndexById($documentId);
            } catch (Exception $e) {
                $log->debug(__METHOD__ . ': ' . 'Removing document-id ' . $documentId . ' from index failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Helper method to add document to index.
     *
     * @param \Opus_Document $document
     * @return void
     */
    private function addDocumentToIndex(\Opus_Document $document)
    {

        $documentId = $document->getId();

        $log = Log::get();

        // create job if asynchronous is set
        if (isset($this->config->runjobs->asynchronous) && filter_var($this->config->runjobs->asynchronous, FILTER_VALIDATE_BOOLEAN)) {
            $log->debug(__METHOD__ . ': ' . 'Adding index job for document ' . $documentId . '.');

            $job = new \Opus_Job();
            $job->setLabel(IndexOpusDocument::LABEL);
            $job->setData([
                'documentId' => $documentId,
                'task' => 'index'
            ]);

            // skip creating job if equal job already exists
            if (true === $job->isUniqueInQueue()) {
                $job->store();
            } else {
                $log->debug(__METHOD__ . ': ' . 'Indexing job for document ' . $documentId . ' already exists!');
            }
        } else {
            $log->debug(__METHOD__ . ': ' . 'Index document ' . $documentId . '.');

            try {
                Service::selectIndexingService('onDocumentChange')->addDocumentsToIndex($document);
            } catch (Exception $e) {
                $log->debug(__METHOD__ . ': ' . 'Indexing document ' . $documentId . ' failed: ' . $e->getMessage());
            } catch (\InvalidArgumentException $e) {
                $log->warn(__METHOD__ . ': ' . $e->getMessage());
            }
        }
    }
}
