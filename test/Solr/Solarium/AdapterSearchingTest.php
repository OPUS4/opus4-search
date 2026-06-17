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
use Opus\Common\Person;
use Opus\Search\Query;
use Opus\Search\QueryFactory;
use Opus\Search\Service;
use Opus\Search\Solr\Solarium\Adapter;
use Opus\Search\Util\Query as QueryUtil;
use Opus\Search\Util\Searcher;
use OpusTest\Search\TestAsset\DocumentBasedTestCase;

use function count;

class AdapterSearchingTest extends DocumentBasedTestCase
{
    public function testService()
    {
        $search = Service::selectSearchingService(null, 'solr');
        $this->assertInstanceOf(Adapter::class, $search);
    }

    public function testDisfunctServiceFails()
    {
        $this->expectException(Exception::class);
        Service::selectSearchingService('disfunct', 'solr');
    }

    public function testEmptyIndex()
    {
        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->customSearch(QueryFactory::selectAllDocuments($search));

        $this->assertEquals(0, $result->getAllMatchesCount());
    }

    public function testEmptyIndexNamed()
    {
        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->namedSearch('alldocs');

        $this->assertEquals(0, $result->getAllMatchesCount());
    }

    public function testSingleDoc()
    {
        $doc = $this->createDocument('article');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex($doc);
        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->customSearch(QueryFactory::selectAllDocuments($search));

        $this->assertEquals(1, $result->getAllMatchesCount());

        $this->assertEquals($doc->getId(), $result->getReturnedMatches()[0]->getId());
    }

    public function testSingleDocNamed()
    {
        $doc = $this->createDocument('article');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex($doc);

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->namedSearch('alldocs');

        $this->assertEquals(1, $result->getAllMatchesCount());

        $this->assertEquals($doc->getId(), $result->getReturnedMatches()[0]->getId());
    }

    public function testClearedIndex()
    {
        $doc = $this->createDocument('article');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex($doc);

        $index->removeDocumentsFromIndexbyId($doc->getId());

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->customSearch(QueryFactory::selectAllDocuments($search));

        $this->assertEquals(0, $result->getAllMatchesCount());
    }

    public function testClearedIndexNamed()
    {
        $doc = $this->createDocument('article');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex($doc);

        $index->removeDocumentsFromIndex($doc);

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->namedSearch('alldocs');

        $this->assertEquals(0, $result->getAllMatchesCount());
    }

    public function testTwoDocs()
    {
        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->customSearch(QueryFactory::selectAllDocuments($search));

        $this->assertEquals(2, $result->getAllMatchesCount());
    }

    public function testTwoDocsNamed()
    {
        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->namedSearch('alldocs');

        $this->assertEquals(2, $result->getAllMatchesCount());
    }

    public function testTwoDocsNamedSpecial()
    {
        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->namedSearch('onedoc');

        $this->assertEquals(2, $result->getAllMatchesCount());
        $this->assertEquals(1, count($result->getReturnedMatches()));
    }

    public function testTwoDocsNamedSpecialAdjusted()
    {
        $docA = $this->createDocument('article');
        $docB = $this->createDocument('book');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $opts = new Query();
        $opts->setRows(1);

        $search = Service::selectSearchingService(null, 'solr');
        $result = $search->namedSearch('alldocs', $opts);

        $this->assertEquals(2, $result->getAllMatchesCount());
        $this->assertEquals(1, count($result->getReturnedMatches()));
    }

    public function testSearchWithDiacritics()
    {
        $docA = $this->createDocument('article');
        $docA->setServerState('published');
        $author = Person::new();
        $author->setLastName('Müller');
        $docA->addPersonAuthor($author);
        $docA->store();

        $docB = $this->createDocument('book');
        $docB->setServerState('published');
        $author = Person::new();
        $author->setLastName('Muller');
        $docB->addPersonAuthor($author);
        $docB->store();

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $search = new Searcher();

        $query = new QueryUtil(QueryUtil::SIMPLE);
        $query->setCatchAll('muller');
        $result = $search->search($query);

        $this->assertEquals(2, $result->getAllMatchesCount());

        $query = new QueryUtil(QueryUtil::SIMPLE);
        $query->setCatchAll('müller');
        $result = $search->search($query);

        $this->assertEquals(2, $result->getAllMatchesCount());
    }

    public function testMapYearFacetIndexFieldsToYearAsset()
    {
        $doc = $this->createDocument('article');
        $doc->setPublishedYear('2012');
        $doc->setServerState('published');
        $docId = $doc->store();

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$doc]);

        $search = new Searcher();

        $query = new QueryUtil(QueryUtil::SIMPLE);
        $query->setCatchAll('*:*'); // TODO why do I have to set this?
        $query->addFilterQuery('published_year', '2012');

        $result = $search->search($query);

        $this->assertEquals(1, $result->getAllMatchesCount());
    }
}
