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
 * @copyright   Copyright (c) 2009-2010 Saechsische Landesbibliothek - Staats- und Universitaetsbibliothek Dresden (SLUB)
 * @copyright   Copyright (c) 2011-2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Task;

use InvalidArgumentException;
use Opus\Common\Document;
use Opus\Common\JobInterface;
use Opus\Job\Worker\InvalidJobException;
use Opus\Job\Worker\WorkerInterface;
use Opus\Search\Service;
use RuntimeException;
use Zend_Log;
use Zend_Log_Writer_Null;

use function is_object;

/**
 * Worker class for indexing Opus documents.
 */
class IndexOpusDocument implements WorkerInterface
{
    const LABEL = 'opus-index-document';

    /**
     * Hold current logger instance.
     *
     * @var Zend_Log
     */
    private $logger;

    /**
     * @param null|mixed $logger (Optional)
     */
    public function __construct($logger = null)
    {
        $this->setLogger($logger);
    }

    /**
     * Return message label that is used to trigger worker process.
     *
     * @return string Message label.
     */
    public function getActivationLabel()
    {
        return self::LABEL;
    }

    /**
     * Set logging facility.
     *
     * @param Zend_Log $logger Logger instance.
     * @throws InvalidArgumentException
     */
    public function setLogger($logger)
    {
        if (null === $logger) {
            $this->logger = new Zend_Log(new Zend_Log_Writer_Null());
        } elseif ($logger instanceof Zend_Log) {
            $this->logger = $logger;
        } else {
            throw new InvalidArgumentException('Zend_Log instance expected.');
        }
    }

    /**
     * Set the search index to add documents to.
     */
    public function setIndex()
    {
        throw new RuntimeException('Indexing service cannot be set programmatically anymore! Use runtime configuration defining solr service named "jobRunner" instead!');
    }

    /**
     * Load a document from database and optional file(s) and index them,
     * or remove document from index (depending on job)
     *
     * @param JobInterface $job Job description and attached data.
     * @throws InvalidJobException
     */
    public function work($job)
    {
        // make sure we have the right job
        if ($job->getLabel() !== $this->getActivationLabel()) {
            throw new InvalidJobException($job->getLabel() . " is not a suitable job for this worker.");
        }

        $data = $job->getData();

        if (! (is_object($data) && isset($data->documentId) && isset($data->task))) {
            throw new InvalidJobException("Incomplete or missing data.");
        }

        if (null !== $this->logger) {
            $this->logger->info('Indexing document with ID: ' . $data->documentId . '.');
        }

        // create index document or remove index, depending on task
        if ($data->task === 'index') {
            $document = Document::get($data->documentId);

            Service::selectIndexingService('jobRunner')->addDocumentsToIndex($document);
        } elseif ($data->task === 'remove') {
            Service::selectIndexingService('jobRunner')->removeDocumentsFromIndexById($data->documentId);
        } else {
            throw new InvalidJobException("unknown task '{$data->task}'.");
        }
    }
}
