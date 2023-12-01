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

use function abs;
use function count;

class AdapterSearchingTest extends DocumentBasedTestCase
{
    /** @var array[] */
    protected static $additionalDocumentPropertySets = [
        'weightedTestDocA' => [
            'TitleMain'     => [
                'Value'    => 'Some Document',
                'Language' => 'eng',
            ],
            'TitleAbstract' => [
                'Value'    => 'Abstract A, full query string (test document) only occurs in abstract.',
                'Language' => 'eng',
            ],
        ],
        'weightedTestDocB' => [
            'TitleMain'     => [
                'Value'    => 'Another Test Document',
                'Language' => 'eng',
            ],
            'TitleAbstract' => [
                'Value'    => 'Abstract of document B, full query string only occurs in title.',
                'Language' => 'eng',
            ],
        ],
        'weightedTestDocC' => [
            'TitleMain'     => [
                'Value'    => 'Third One',
                'Language' => 'eng',
            ],
            'TitleAbstract' => [
                'Value'    => 'Abstract C, first query term (test) only occurs in abstract.\nSome more text.',
                'Language' => 'eng',
            ],
        ],
        'weightedTestDocD' => [
            'TitleMain'     => [
                'Value'    => 'Fourth One',
                'Language' => 'eng',
            ],
            'TitleAbstract' => [
                'Value'    => 'Abstract D, second query term (document) only occurs in abstract.\nEven more text.',
                'Language' => 'eng',
            ],
        ],
        'weightedTestDocE' => [
            'TitleMain'     => [
                'Value'    => 'Yet Another Test',
                'Language' => 'eng',
            ],
            'TitleAbstract' => [
                'Value'    => 'Abstract of document E, title & abstract contain one query term each.',
                'Language' => 'eng',
            ],
        ],
    ];

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

    public function testStandardAndWeightedSearch()
    {
        $docA = $this->createDocument('weightedTestDocA');
        $docB = $this->createDocument('weightedTestDocB');
        $docC = $this->createDocument('weightedTestDocC');
        $docD = $this->createDocument('weightedTestDocD');
        $docE = $this->createDocument('weightedTestDocE');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB, $docC, $docD, $docE]);

        $search = Service::selectSearchingService(null, 'solr');

        $query = new Query();
        $query->addSorting('score', false);

        // add query terms
        $filter = $search->createFilter();
        $filter->createSimpleEqualityFilter('*')->addValue('test document');
        $query->setFilter($filter);

        // 1. standard search (AND)
        $query->setWeightedSearch(false);
        $query->setUnion(false); // use AND as default query operator

        $result  = $search->customSearch($query);
        $matchingIds = $result->getReturnedMatchingIds();

        $this->assertEquals(3, count($matchingIds));

        $this->assertTrue(in_array($docA->getId(), $matchingIds));
        $this->assertTrue(in_array($docB->getId(), $matchingIds));
        $this->assertTrue(in_array($docE->getId(), $matchingIds));

        // 2. weighted search (AND)
        $query->setWeightedSearch(true);
        $query->setWeightedFields(['abstract' => 1.0, 'title' => 1.0]); // assigns boost factors to fields
        $query->setWeightMultiplier(5); // multiplier to further increase boost factors when matching phrases
        $query->setUnion(false); // use AND as default query operator

        $result  = $search->customSearch($query);
        $matchingIds = $result->getReturnedMatchingIds();

        $this->assertEquals(2, count($matchingIds));

        $this->assertTrue(in_array($docA->getId(), $matchingIds));
        $this->assertTrue(in_array($docB->getId(), $matchingIds));

        // 3. weighted search (OR), expect much greater scores for the two documents matching the full query phrase
        $query->setUnion(true); // use OR as default query operator

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(5, count($matches));

        $this->assertTrue($matches[0]->getScore() > 1.0);
        $this->assertTrue($matches[1]->getScore() > 1.0);

        $this->assertTrue($matches[2]->getScore() < 1.0);
        $this->assertTrue($matches[3]->getScore() < 1.0);
        $this->assertTrue($matches[4]->getScore() < 1.0);
    }

    public function testWeightedSearch()
    {
        $docA = $this->createDocument('weightedTestDocA');
        $docB = $this->createDocument('weightedTestDocB');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');

        $query = new Query();
        $query->addSorting('score', false);

        $filter = $search->createFilter();
        $filter->createSimpleEqualityFilter('*')->addValue('test document');
        $query->setFilter($filter);

        // 1. with different boost factors assigned to fields, expect clearly different scores & appropriate sort order
        $this->adjustConfiguration([
            'search' => [
                'weightedSearch' => true, // use the Solr eDisMax query parser
                'simple'         => [
                    'abstract' => 0.5,
                    'title'    => 10,
                ],
            ],
        ]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) > 1.0);

        $this->assertEquals($docB->getId(), $matches[0]->getDocument()->getId());

        // 2. with swapped boost factors, expect a swapped sort order
        $query->setWeightedFields(['abstract' => 10.0, 'title' => 0.5]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) > 1.0);

        $this->assertEquals($docA->getId(), $matches[0]->getDocument()->getId());
    }

    public function testWeightedSearchWithEqualWeights()
    {
        $docA = $this->createDocument('weightedTestDocA');
        $docB = $this->createDocument('weightedTestDocB');

        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');

        $query = new Query();
        $query->setWeightedSearch(true);

        $filter = $search->createFilter();
        $filter->createSimpleEqualityFilter('*')->addValue('test document');
        $query->setFilter($filter);

        // 1. without any boost factors assigned to fields, expect roughly equal scores
        $query->setWeightedFields([]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) < 1.0);

        // 2. with equal boost factors, expect roughly equal scores
        $query->setWeightedFields(['abstract' => 1.0, 'title' => 1.0]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) < 1.0);
    }
}
