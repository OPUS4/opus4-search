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
use Opus\Common\Document;
use Opus\Common\Person;
use Opus\Search\Query;
use Opus\Search\QueryFactory;
use Opus\Search\SearchingInterface;
use Opus\Search\Service;
use Opus\Search\Solr\Solarium\Adapter;
use Opus\Search\Util\Query as QueryUtil;
use Opus\Search\Util\Searcher;
use OpusTest\Search\TestAsset\DocumentBasedTestCase;

use function abs;
use function count;
use function in_array;

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

    /**
     * Test that a standard `AND` search (which uses Solr's standard query parser)
     * finds all documents that contain both query terms in the same field.
     */
    public function testStandardAndSearch()
    {
        // TODO when the `text` field gets removed from the Solr index, weightedTestDocE should not get found anymore
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $docC = $this->createDocument('weightedTestDocC'); // has only one query term (in abstract)
        $docD = $this->createDocument('weightedTestDocD'); // has only one query term (in abstract)
        $docE = $this->createDocument('weightedTestDocE'); // title & abstract contain one query term each
        $this->indexDocuments([$docA, $docB, $docC, $docD, $docE]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(false); // use Solr's standard query parser
        $query->setUnion(false); // use AND as default query operator

        $result      = $search->customSearch($query);
        $matchingIds = $result->getReturnedMatchingIds();

        $this->assertEquals(3, count($matchingIds));

        // expect only documents that contain both query terms in the same field
        $this->assertTrue(in_array($docA->getId(), $matchingIds));
        $this->assertTrue(in_array($docB->getId(), $matchingIds));
        $this->assertTrue(in_array($docE->getId(), $matchingIds));
    }

    /**
     * Test that a weighted `AND` search finds all documents that contain both
     * query terms in the same field.
     */
    public function testWeightedAndSearchWithoutBoosts()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $docC = $this->createDocument('weightedTestDocC'); // has only one query term (in abstract)
        $docD = $this->createDocument('weightedTestDocD'); // has only one query term (in abstract)
        $docE = $this->createDocument('weightedTestDocE'); // title & abstract contain one query term each
        $this->indexDocuments([$docA, $docB, $docC, $docD, $docE]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true); // use Solr's eDisMax query parser
        $query->setWeightedFields(['abstract' => 1.0, 'title' => 1.0]); // assigns boost factors to fields
        $query->setUnion(false); // use AND as default query operator

        $result      = $search->customSearch($query);
        $matchingIds = $result->getReturnedMatchingIds();

        $this->assertEquals(2, count($matchingIds));

        // expect only documents that contain both query terms in the same field
        $this->assertTrue(in_array($docA->getId(), $matchingIds));
        $this->assertTrue(in_array($docB->getId(), $matchingIds));
    }

    /**
     * Test that a weighted `OR` search finds all documents that contain at least
     * one query term in one of their fields.
     */
    public function testWeightedOrSearchWithoutBoosts()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $docC = $this->createDocument('weightedTestDocC'); // has only one query term (in abstract)
        $docD = $this->createDocument('weightedTestDocD'); // has only one query term (in abstract)
        $docE = $this->createDocument('weightedTestDocE'); // title & abstract contain one query term each
        $this->indexDocuments([$docA, $docB, $docC, $docD, $docE]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true);
        $query->setWeightedFields(['abstract' => 1.0, 'title' => 1.0]);
        $query->setUnion(true); // use OR as default query operator

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        // expect all of the above documents to get found
        $this->assertEquals(5, count($matches));
    }

    /**
     * Test that a weighted `OR` search with boosted phrase matching results in increased
     * importance given to search results containing an exact occurrence of the search string.
     */
    public function testWeightedOrSearchWithBoostedPhraseMatching()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $docC = $this->createDocument('weightedTestDocC'); // has only one query term (in abstract)
        $docD = $this->createDocument('weightedTestDocD'); // has only one query term (in abstract)
        $docE = $this->createDocument('weightedTestDocE'); // title & abstract contain one query term each
        $this->indexDocuments([$docA, $docB, $docC, $docD, $docE]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true);
        $query->setWeightedFields(['abstract' => 1.0, 'title' => 1.0]);
        $query->setWeightMultiplier(5); // multiplier to further increase boost factors when matching phrases
        $query->setUnion(true); // use OR as default query operator

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(5, count($matches));

        // expect the two documents matching the exact occurrence of the search string to sort first
        $highestScoringIds = [$matches[0]->getDocument()->getId(), $matches[1]->getDocument()->getId()];
        $this->assertTrue(in_array($docA->getId(), $highestScoringIds));
        $this->assertTrue(in_array($docB->getId(), $highestScoringIds));

        // expect much greater scores for the two documents matching the exact occurrence of the search string
        $this->assertTrue($matches[0]->getScore() > 1.0);
        $this->assertTrue($matches[1]->getScore() > 1.0);

        $this->assertTrue($matches[2]->getScore() < 1.0);
        $this->assertTrue($matches[3]->getScore() < 1.0);
        $this->assertTrue($matches[4]->getScore() < 1.0);
    }

    /**
     * Test that a weighted `AND` search with a field's boost factor set to 0 will
     * cause a document with a match just in that field to get a score of 0.
     */
    public function testWeightedAndSearchWithZeroedBoost()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $docE = $this->createDocument('weightedTestDocE'); // title & abstract contain one query term each
        $this->indexDocuments([$docA, $docB, $docE]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true);
        $query->setWeightedFields(['abstract' => 0, 'title' => 1.0]);
        $query->setUnion(false); // use AND as default query operator

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        // expect only docA & docB to get found (which both contain the full query string in one of their fields)
        $this->assertEquals(2, count($matches));

        // expect docB (contains full query string in title) to sort first and with a score greater than 0
        $this->assertEquals($docB->getId(), $matches[0]->getDocument()->getId());
        $this->assertTrue($matches[0]->getScore() > 0.0);

        // expect docA (contains full query string in abstract) to sort last and with a score of 0
        $this->assertEquals($docA->getId(), $matches[1]->getDocument()->getId());
        $this->assertTrue($matches[1]->getScore() === 0.0);
    }

    /**
     * Test that a weighted `OR` search with a field's boost factor set to 0 will
     * cause a document with a match just in that field to get a score of 0.
     */
    public function testWeightedOrSearchWithZeroedBoost()
    {
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $docD = $this->createDocument('weightedTestDocD'); // has only one query term (in abstract)
        $docE = $this->createDocument('weightedTestDocE'); // title & abstract contain one query term each
        $this->indexDocuments([$docB, $docD, $docE]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true);
        $query->setWeightedFields(['abstract' => 0, 'title' => 1.0]);
        $query->setUnion(true); // use OR as default query operator

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        // expect all documents to get found since all of them contain at least one query term in one of their fields
        $this->assertEquals(3, count($matches));

        // expect docB (contains full query string in title) to sort first and with a score greater than 0
        $this->assertEquals($docB->getId(), $matches[0]->getDocument()->getId());
        $this->assertTrue($matches[0]->getScore() > 0.0);

        // expect docE (contains part of query string in title) to sort in the middle and with a score greater than 0
        $this->assertEquals($docE->getId(), $matches[1]->getDocument()->getId());
        $this->assertTrue($matches[1]->getScore() > 0.0);

        // expect docD (contains part of query string in abstract) to sort last and with a score of 0
        $this->assertEquals($docD->getId(), $matches[2]->getDocument()->getId());
        $this->assertTrue($matches[2]->getScore() === 0.0);
    }

    /**
     * Test that a weighted search with equal weights (i.e. no fields being boosted) will result in
     * similar scores for two documents that both contain the full query string in one of their fields.
     */
    public function testWeightedSearchWithEqualWeights()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $this->indexDocuments([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true);

        // 1. without any boost factors assigned to fields, expect roughly equal scores
        $query->setWeightedFields([]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) < 1.0);

        // 2. with equal boost factors, also expect roughly equal scores
        $query->setWeightedFields(['abstract' => 1.0, 'title' => 1.0]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) < 1.0);
    }

    /**
     * Test that a weighted search with different boost factors assigned to fields will influence
     * result scores accordingly & cause a document with a match in a boosted field to sort first.
     */
    public function testWeightedSearchWithBoostedFields()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $this->indexDocuments([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $this->adjustConfiguration([
            'search' => [
                'weightedSearch' => true, // use the Solr eDisMax query parser
                'simple'         => [
                    'abstract' => 0.5, // decrease importance of abstract field
                    'title'    => 10, // increase importance of title field
                ],
            ],
        ]);

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        // expect clearly different scores between a document with a match in the boosted title field and one without
        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) > 1.0);

        // expect the document containing the query string in the boosted title field to sort first
        $this->assertEquals($docB->getId(), $matches[0]->getDocument()->getId());
    }

    /**
     * Test that a weighted search with (compared to the previous test) swapped boost factors
     * will also cause the sort order of search results to get swapped.
     */
    public function testWeightedSearchWithBoostedFieldsSwapped()
    {
        $docA = $this->createDocument('weightedTestDocA'); // full query string only occurs in abstract
        $docB = $this->createDocument('weightedTestDocB'); // full query string only occurs in title
        $this->indexDocuments([$docA, $docB]);

        $search = Service::selectSearchingService(null, 'solr');
        $query  = $this->queryWithSearchString($search, 'test document');

        $query->setWeightedSearch(true);
        $query->setWeightedFields(['abstract' => 10.0, 'title' => 0.5]); // increase importance of abstract field

        $result  = $search->customSearch($query);
        $matches = $result->getReturnedMatches();

        $this->assertEquals(2, count($matches));

        // expect clearly different scores between a document with a match in the boosted abstract field and one without
        $this->assertTrue(abs($matches[0]->getScore() - $matches[1]->getScore()) > 1.0);

        // expect the document containing the query string in the boosted abstract field to sort first
        $this->assertEquals($docA->getId(), $matches[0]->getDocument()->getId());
    }

    /**
     * Adds the given documents to the Solr index.
     *
     * @param Document[] $documents documents to be indexed
     */
    protected function indexDocuments($documents)
    {
        $index = Service::selectIndexingService(null, 'solr');
        $index->addDocumentsToIndex($documents);
    }

    /**
     * Returns a query object for the given search string sorting results by score in descending order.
     *
     * @param SearchingInterface $search searching service to work with
     * @param string             $searchString query string to search for
     * @return Query
     */
    protected function queryWithSearchString($search, $searchString)
    {
        $query = new Query();
        $query->addSorting('score', false);

        // add query terms
        $filter = $search->createFilter();
        $filter->createSimpleEqualityFilter('*')->addValue($searchString);
        $query->setFilter($filter);

        return $query;
    }
}
