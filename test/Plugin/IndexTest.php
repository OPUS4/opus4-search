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
 * @category    Tests
 * @package     Opus_Document_Plugin
 * @author      Edouard Simon edouard.simon@zib.de
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2010-2019, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Plugin;

use Opus\Document;
use Opus\Job;
use OpusTest\Search\TestAsset\TestCase;

class IndexTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
        \Zend_Registry::get('Zend_Config')->merge(
            new \Zend_Config(['runjobs' => ['asynchronous' => self::CONFIG_VALUE_TRUE]])
        );
    }

    public function testCreateIndexJob()
    {
        $indexJobsBefore = Job::getByLabels(['opus-index-document']);
        $jobCountBefore = count($indexJobsBefore);

        $document = Document::new();
        $document->setServerState('published');
        $documentId = $document->store();

        $indexJobs = Job::getByLabels(['opus-index-document']);

        $this->assertEquals(++$jobCountBefore, count($indexJobs), 'Expected new job');

        $newJob = $this->getCreatedJob($documentId, $indexJobs);

        $this->assertNotNull($newJob, 'Expected new job');
        $this->assertEquals('index', $newJob->getData()->task);

        $document->delete();
        if (! is_null($newJob)) {
            $newJob->delete();
        }
    }

    public function testDoNotCreateIndexJobIfAsyncDisabled()
    {
        \Zend_Registry::get('Zend_Config')->runjobs->asynchronous = 0;

        $indexJobsBefore = Job::getByLabels(['opus-index-document']);
        $jobCountBefore = count($indexJobsBefore);

        $document = Document::new();
        $document->setServerState('published');
        $documentId = $document->store();

        $indexJobs = Job::getByLabels(['opus-index-document']);

        $this->assertEquals(
            $jobCountBefore,
            count($indexJobs),
            'Expected equal job count before and after storing document.'
        );

        $newJob = $this->getCreatedJob($documentId, $indexJobs);
        $this->assertNull($newJob, 'Expected that no job was created');
    }

    public function testCreateRemoveIndexJob()
    {
        $removeIndexJobsBefore = Job::getByLabels(['opus-index-document']);
        $jobCountBefore = count($removeIndexJobsBefore);

        $document = Document::new();
        $document->setServerState('published');
        $documentId = $document->store();

        $indexJobs = Job::getByLabels(['opus-index-document']);
        $newIndexJob = $this->getCreatedJob($documentId, $indexJobs);
        $this->assertNotNull($newIndexJob, 'Expected new opus-index-document job');

        if (! is_null($newIndexJob)) {
            $newIndexJob->delete();
        }

        $document->setServerState(Document::STATE_DELETED);
        $document->store();

        $indexJobs = Job::getByLabels(['opus-index-document']);

        $this->assertEquals(
            ++$jobCountBefore,
            count($indexJobs),
            'Expected increased opus-index-document job count'
        );

        $newJob = $this->getCreatedJob($documentId, $indexJobs);
        $this->assertNotNull($newJob, 'Expected new opus-index-document job');
        $this->assertEquals('index', $newJob->getData()->task);

        Job::deleteAll();

        $jobCountBefore = 0;

        $document->delete();

        $removeIndexJobs = Job::getByLabels(['opus-index-document']);

        $this->assertEquals(
            ++$jobCountBefore,
            count($removeIndexJobs),
            'Expected increased opus-index-document job count'
        );

        $newJob = $this->getCreatedJob($documentId, $removeIndexJobs);
        $this->assertNotNull($newJob, 'Expected new opus-index-document job');
        $this->assertEquals('remove', $newJob->getData()->task);
    }

    public function testDoNotCreateRemoveIndexJobIfAsyncDisabled()
    {
        \Zend_Registry::get('Zend_Config')->runjobs->asynchronous = 0;

        $removeIndexJobsBefore = Job::getByLabels(['opus-remove-index-document']);
        $jobCountBefore = count($removeIndexJobsBefore);

        $document = Document::new();
        $document->setServerState('published');
        $documentId = $document->store();

        $newIndexJob = null;
        $indexJobs = Job::getByLabels(['opus-index-document']);
        $newIndexJob = $this->getCreatedJob($documentId, $indexJobs);
        $this->assertNull($newIndexJob, 'Expected that no opus-index-document job was created');

        if (! is_null($newIndexJob)) {
            $newIndexJob->delete();
        }

        $document->setServerState(Document::STATE_DELETED);
        $document->store();

        $removeIndexJobs = Job::getByLabels(['opus-remove-index-document']);
        $this->assertEquals(
            $jobCountBefore,
            count($removeIndexJobs),
            'Expected equal job count before and after storing document.'
        );

        $newJob = $this->getCreatedJob($documentId, $removeIndexJobs);
        $this->assertNull($newJob, 'Expected that no new opus-remove-index-document job was created');
    }

    private function getCreatedJob($documentId, $jobs)
    {
        $newJob = null;
        foreach ($jobs as $job) {
            $jobData = $job->getData(true);
            if (isset($jobData['documentId']) && $jobData['documentId'] == $documentId) {
                $newJob = $job;
                break;
            }
        }
        return $newJob;
    }
}
