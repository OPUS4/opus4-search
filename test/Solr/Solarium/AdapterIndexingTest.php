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
 * @copyright   Copyright (c) 2009, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Solr\Solarium;

use Exception;
use Opus\Document\Plugin\XmlCache;
use Opus\Search\Plugin\Index;
use Opus\Search\QueryFactory;
use Opus\Search\Service;
use Opus\Search\Solr\Solarium\Adapter;
use OpusTest\Search\TestAsset\DocumentBasedTestCase;

class AdapterIndexingTest extends DocumentBasedTestCase
{
    public function testService()
    {
        $service = Service::selectIndexingService(null, 'solr');
        $this->assertInstanceOf(Adapter::class, $service);
    }

    public function testDisfunctServiceFails()
    {
        $this->expectException(Exception::class);
        Service::selectIndexingService('disfunct');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemoveAllDocuments()
    {
        $service = Service::selectIndexingService(null, 'solr');
        $service->removeAllDocumentsFromIndex();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingArticleWithoutFiles()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingBookWithoutFiles()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('book');

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingArticleWithPublicFile()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $this->addFileToDocument($doc, "test.pdf", "PDF Document", true);

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingArticleWithPublicFiles()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $this->addFileToDocument($doc, "test.pdf", "PDF Document", true);
        $this->addFileToDocument($doc, "test.ps", "PS Document", true);

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingArticleWithHiddenFile()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $this->addFileToDocument($doc, "test.pdf", "PDF Document", false);

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingArticleWithHiddenFiles()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $this->addFileToDocument($doc, "test.pdf", "PDF Document", false);
        $this->addFileToDocument($doc, "test.ps", "PS Document", false);

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingArticleWithMixedFiles()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $this->addFileToDocument($doc, "test.pdf", "PDF Document", false);
        $this->addFileToDocument($doc, "test.ps", "PS Document", true);
        $this->addFileToDocument($doc, "test.html", "HTML Document", true);
        $this->addFileToDocument($doc, "test.odt", "ODT Document", false);

        $service->addDocumentsToIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingIndexedArticle()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);

        $service->removeDocumentsFromIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingIndexedArticleById()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);

        $service->removeDocumentsFromIndexById($doc->getId());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingIndexedArticleTwiceFails()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);

        $service->removeDocumentsFromIndex($doc);
        $service->removeDocumentsFromIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingIndexedArticleByIdTwiceFails()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);

        $service->removeDocumentsFromIndexById($doc->getId());
        $service->removeDocumentsFromIndexById($doc->getId());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMixedRemovingIndexedArticleTwiceFails()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);

        $service->removeDocumentsFromIndex($doc);
        $service->removeDocumentsFromIndexById($doc->getId());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMixedRemovingIndexedArticleTwiceFailsAgain()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);

        $service->removeDocumentsFromIndexById($doc->getId());
        $service->removeDocumentsFromIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingNonIndexedArticleFails()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->removeDocumentsFromIndex($doc);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingNonIndexedArticleByIdFails()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $doc = $this->createDocument('article');

        $service->removeDocumentsFromIndexById($doc->getId());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testIndexingMultipleDocument()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');
        $docC = $this->createDocument('monograph');
        $docD = $this->createDocument('report');

        $service->addDocumentsToIndex([$docA, $docB, $docC, $docD]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRemovingMultipleIndexedDocuments()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');
        $docC = $this->createDocument('monograph');
        $docD = $this->createDocument('report');

        $service->addDocumentsToIndex([$docA, $docB, $docC, $docD]);
        $service->removeDocumentsFromIndex([$docA, $docB, $docC, $docD]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMultiplyRemovingMultipleIndexedDocumentsFails()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');
        $docC = $this->createDocument('monograph');
        $docD = $this->createDocument('report');

        $service->addDocumentsToIndex([$docA, $docB, $docC, $docD]);
        $service->removeDocumentsFromIndex([$docA, $docB, $docC, $docD]);
        $service->removeDocumentsFromIndex([$docA, $docB, $docC, $docD]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMultiplyRemovingMultipleIndexedDocumentsFailsAgain()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');
        $docC = $this->createDocument('monograph');
        $docD = $this->createDocument('report');

        $service->addDocumentsToIndex([$docA, $docB, $docC, $docD]);
        $service->removeDocumentsFromIndex([$docA, $docB, $docC, $docD]);
        $service->removeDocumentsFromIndex([$docA]);
    }

    public function testIndexingArticleOnDisfunctServiceFails()
    {
        $this->expectException(Exception::class);
        $service = Service::selectIndexingService('disfunct', 'solr');

        // TODO test never gets here - clean up
        $doc = $this->createDocument('article');

        $service->addDocumentsToIndex($doc);
    }

    public function testIndexingAuthorWithoutFirstName()
    {
        $this->markTestIncomplete('Add tests for OPUSVIER-3890');
    }

    public function testTemporaryDocumentsAreNotIndexed()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $document = $this->createDocument('article');
        $document->setServerState('temporary');
        $docId = $document->store();

        $service->addDocumentsToIndex($document);

        $search = Service::selectSearchingService(null, 'solr');

        $result = $search->customSearch(QueryFactory::selectDocumentById($search, $docId));

        $this->assertEquals(0, $result->getAllMatchesCount());

        // prevent automatic indexing - cache currently triggers indexing directly
        $document->unregisterPlugin(Index::class);
        $document->unregisterPlugin(XmlCache::class);

        $document->setServerState('unpublished');
        $document->store();

        $service->addDocumentsToIndex($document);

        $result = $search->customSearch(QueryFactory::selectDocumentById($search, $docId));

        $this->assertEquals(1, $result->getAllMatchesCount());
    }
}
