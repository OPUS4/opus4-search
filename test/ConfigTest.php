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

namespace OpusTest\Search;

use Opus\Common\EnrichmentKey;
use Opus\Search\Config;
use OpusTest\Search\TestAsset\TestCase;
use Zend_Config;

use function array_unique;
use function array_values;

class ConfigTest extends TestCase
{
    public function testProvidesSearchConfiguration()
    {
        $config = Config::getServiceConfiguration('search', null, 'solr');

        $this->assertInstanceOf(Zend_Config::class, $config);
        $this->assertInstanceOf(Zend_Config::class, $config->query);
        $this->assertInstanceOf(Zend_Config::class, $config->query->alldocs);

        $this->assertEquals('search', $config->marker);
        $this->assertNotNull($config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertNotNull($config->endpoint->primary->path);

        $this->assertNotNull($config->endpoint->primary->timeout);
        $this->assertEquals(10, $config->endpoint->primary->timeout);
    }

    public function testProvidesIndexConfiguration()
    {
        $config = Config::getServiceConfiguration('index', null, 'solr');

        $this->assertInstanceOf(Zend_Config::class, $config);
        $this->assertInstanceOf(Zend_Config::class, $config->query);
        $this->assertInstanceOf(Zend_Config::class, $config->query->alldocs);

        $this->assertEquals('index', $config->marker);
        $this->assertNotNull($config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertNotNull($config->endpoint->primary->path);
    }

    public function testProvidesExtractConfiguration()
    {
        $config = Config::getServiceConfiguration('extract', null, 'solr');

        $this->assertInstanceOf(Zend_Config::class, $config);
        $this->assertInstanceOf(Zend_Config::class, $config->query);
        $this->assertInstanceOf(Zend_Config::class, $config->query->alldocs);

        $this->assertEquals('extract', $config->marker);
        $this->assertNotNull($config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertNotNull($config->endpoint->primary->path);
    }

    public function testProvidesDefaultConfiguration()
    {
        $config = Config::getServiceConfiguration('default', null, 'solr');

        $this->assertInstanceOf(Zend_Config::class, $config);
        $this->assertInstanceOf(Zend_Config::class, $config->query);
        $this->assertInstanceOf(Zend_Config::class, $config->query->alldocs);

        $this->assertEquals('default', $config->marker);
        $this->assertNotNull($config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertNotNull($config->endpoint->primary->path);
    }

    public function testProvidesSpecialSearchConfiguration()
    {
        $config = Config::getServiceConfiguration('search', 'special', 'solr');

        $this->assertInstanceOf('Zend_Config', $config);
        $this->assertInstanceOf('Zend_Config', $config->query);
        $this->assertInstanceOf('Zend_Config', $config->query->alldocs);

        $this->assertEquals('search2', $config->marker);
        $this->assertEquals('127.0.0.2', $config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertEquals('/solr-special/', $config->endpoint->primary->path);
    }

    public function testProvidesSpecialExtractConfiguration()
    {
        $config = Config::getServiceConfiguration('extract', 'special', 'solr');

        $this->assertInstanceOf('Zend_Config', $config);
        $this->assertInstanceOf('Zend_Config', $config->query);
        $this->assertInstanceOf('Zend_Config', $config->query->alldocs);

        $this->assertEquals('extract2', $config->marker);
        $this->assertNotNull($config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertEquals('/solr-special/', $config->endpoint->primary->path);
    }

    public function testProvidesDefaultConfigurationAsFallback()
    {
        $config = Config::getServiceConfiguration('missing', null, 'solr');

        $this->assertInstanceOf('Zend_Config', $config);
        $this->assertInstanceOf('Zend_Config', $config->query);
        $this->assertInstanceOf('Zend_Config', $config->query->alldocs);

        $this->assertEquals('default', $config->marker);
        $this->assertNotNull($config->endpoint->primary->host);
        $this->assertNotNull($config->endpoint->primary->port);
        $this->assertNotNull($config->endpoint->primary->path);
    }

    public function testProvidesAllSolrConfiguration()
    {
        $config = Config::getDomainConfiguration('solr');

        $this->assertInstanceOf('Zend_Config', $config);
        $this->assertInstanceOf('Zend_Config', $config->default);
        $this->assertInstanceOf('Zend_Config', $config->special);
    }

    public function testProvidesCachedConfiguration()
    {
        $configA = Config::getServiceConfiguration('search');
        $configB = Config::getServiceConfiguration('search');

        $this->assertTrue($configA === $configB);

        Config::dropCached();

        $configC = Config::getServiceConfiguration('search');

        $this->assertTrue($configA !== $configC);
    }

    public function testAccessingDisfunctSearchConfiguration()
    {
        $config = Config::getServiceConfiguration('search', 'disfunct');

        $this->assertInstanceOf('Zend_Config', $config);
        $this->assertInstanceOf('Zend_Config', $config->query);
        $this->assertInstanceOf('Zend_Config', $config->query->alldocs);

        $this->assertEquals('search', $config->marker);
        $this->assertEquals('1.2.3.4', $config->endpoint->primary->host);
        $this->assertEquals('12345', $config->endpoint->primary->port);
        $this->assertEquals('/solr-disfunct/', $config->endpoint->primary->path);
    }

    public function testAccessingDisfunctIndexConfiguration()
    {
        $config = Config::getServiceConfiguration('index', 'disfunct');

        $this->assertInstanceOf('Zend_Config', $config);
        $this->assertInstanceOf('Zend_Config', $config->query);
        $this->assertInstanceOf('Zend_Config', $config->query->alldocs);

        $this->assertEquals('index', $config->marker);
        $this->assertEquals('1.2.3.4', $config->endpoint->primary->host);
        $this->assertEquals('12345', $config->endpoint->primary->port);
        $this->assertEquals('/solr-disfunct/', $config->endpoint->primary->path);
    }

    public function testGetFacetFieldsServerStateAdded()
    {
        $config = Config::getDomainConfiguration();

        $facetList = $config->facets;

        $this->assertStringNotContainsString(
            'server_state',
            $facetList,
            'Facet configuration for testing should not contain server_state'
        );

        $facets = Config::getFacetFields();

        $this->assertContains('server_state', $facets);
    }

    public function testGetFacetFieldsContainsEnrichments()
    {
        $this->markTestIncomplete('not fully implemented yet');
    }

    public function testGetEnrichmentFacets()
    {
        $this->markTestIncomplete('not fully implemented yet');
        $enrichment = EnrichmentKey::fetchByName('test');

        if ($enrichment === null) {
            $enrichment = EnrichmentKey::new();
            $enrichment->setName('test');
            $enrichment->store();
        }

        $enrichments = Config::getEnrichmentFacets();

        $this->assertCount(1, $enrichments);
        $this->assertContains('enrichment_test', $enrichments);
    }

    public function testGetFacetLimits()
    {
        $limits = Config::getFacetLimits();

        $this->assertIsArray($limits);
        $this->assertCount(11, $limits);
        $this->assertArrayHasKey('__global__', $limits);
        $this->assertEquals(10, $limits['__global__']);
        $this->assertArrayHasKey('author_facet', $limits);
        $this->assertEquals(10, $limits['author_facet']);
        $this->assertEquals(10, $limits['subject']);
        $this->assertEquals(10, $limits['published_year']);

        Config::dropCached();

        $this->adjustConfiguration([
            'searchengine' => [
                'solr' => [
                    'globalfacetlimit' => 20,
                    'facetlimit'       => ['subject' => 30],
                ],
            ],
            'search'       => ['facet' => ['year' => ['limit' => 15]]],
        ]);

        $limits = Config::getFacetLimits();

        $this->assertEquals(20, $limits['__global__']);
        $this->assertArrayHasKey('author_facet', $limits);
        $this->assertEquals(20, $limits['author_facet']);
        $this->assertEquals(30, $limits['subject']);
        $this->assertEquals(15, $limits['published_year']);
    }

    public function testGetFacetLimitsDefault()
    {
        $this->adjustConfiguration([
            'searchengine' => [
                'solr' => [
                    'globalfacetlimit' => 20,
                ],
            ],
            'search'       => [
                'facet' => [
                    'default' => ['limit' => 15],
                    'year'    => ['limit' => 30],
                ],
            ],
        ]);

        $limits = Config::getFacetLimits();

        $this->assertEquals(15, $limits['__global__']);
        $this->assertArrayHasKey('author_facet', $limits);
        $this->assertEquals(15, $limits['subject']);
        $this->assertEquals(30, $limits['published_year']);
    }

    public function testGetFacetSorting()
    {
        $sorting = Config::getFacetSorting();

        $this->assertIsArray($sorting);
        $this->assertCount(0, $sorting);

        Config::dropCached();

        $this->adjustConfiguration([
            'searchengine' => ['solr' => ['sortcrit' => ['year' => 'lexi']]],
            'search'       => ['facet' => ['subject' => ['sort' => 'lexi']]],
        ]);

        $sorting = Config::getFacetSorting();

        $this->assertCount(2, $sorting);
        $this->assertEquals([
            'published_year' => 'index',
            'subject'        => 'index',
        ], $sorting);
    }

    public function testGetFacetSortingDefault()
    {
        $this->adjustConfiguration([
            'searchengine' => ['solr' => ['sortcrit' => ['year' => 'count']]],
            'search'       => [
                'facet' => [
                    'default' => ['sort' => 'lexi'],
                    'subject' => ['sort' => 'count'],
                ],
            ],
        ]);

        $sorting = Config::getFacetSorting();

        $this->assertCount(9, $sorting);
        $this->assertArrayNotHasKey('subject', $sorting);
        $this->assertArrayNotHasKey('year', $sorting);

        // there should only be 'index' as sorting value in array
        $sorting = array_unique(array_values($sorting));
        $this->assertCount(1, $sorting);
        $this->assertContains('index', $sorting);
    }

    public function testGetFacetFieldsWithMapping()
    {
        $this->adjustConfiguration([
            'search' => [
                'facet' => [
                    'year' => ['indexField' => 'completed_year'],
                ],
            ],
        ]);

        $facets = Config::getFacetFields();

        $this->assertNotContains('year', $facets);
        $this->assertContains('completed_year', $facets);
    }
}
