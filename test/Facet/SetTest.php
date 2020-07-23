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
 * @category    Application
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2008-2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Facet;

use Opus\Search\Facet\Set;
use OpusTest\Search\TestAsset\SimpleTestCase;

class SetTest extends SimpleTestCase
{

    public function testOverrideLimitsFluent()
    {
        $facets = Set::create();

        $this->assertEquals($facets, $facets->overrideLimits(20));
    }

    public function testOverrideLimits()
    {
        $facets = Set::create();

        $facets->addField('author_facet');

        $fields = $facets->getFields();

        $this->assertCount(1, $fields);
        $this->assertArrayHasKey('author_facet', $fields);

        $authorField = $fields['author_facet'];

        $this->assertEquals(10, $authorField->getLimit());

        $facets->overrideLimits([
            'author_facet' => 10000
        ]);

        // this causes the field to be updated after the limit change
        $facets->setFields(['author_facet', 'year']);

        $fields = $facets->getFields();

        $this->assertArrayHasKey('author_facet', $fields);

        $authorField = $fields['author_facet'];

        $this->assertEquals(10000, $authorField->getLimit());

        // check other field still has limit
        $this->assertArrayHasKey('published_year', $fields);

        $yearField = $fields['published_year'];

        $this->assertEquals(10, $yearField->getLimit());
    }

    public function testOverrideLimitsCustomValuesArePreserved()
    {
        $facets = Set::create();

        $facets->addField('author_facet');

        $fields = $facets->getFields();

        $this->assertCount(1, $fields);
        $this->assertArrayHasKey('author_facet', $fields);

        $authorField = $fields['author_facet'];

        $this->assertEquals(10, $authorField->getLimit());

        $facets->overrideLimits([
            'author_facet' => 20
        ]);

        $facets->overrideLimits([
            'year' => 30
        ]);

        // this causes the field to be updated after the limit change
        $facets->setFields(['author_facet', 'year']);

        $fields = $facets->getFields();

        $this->assertArrayHasKey('author_facet', $fields);

        $authorField = $fields['author_facet'];

        $this->assertEquals(20, $authorField->getLimit());

        // check other field still has limit
        $this->assertArrayHasKey('published_year', $fields);

        $yearField = $fields['published_year']; // Name of index field used for search

        $this->assertEquals(30, $yearField->getLimit());
    }

    public function testOverrideGlobalLimits()
    {
        $facets = Set::create();

        $facets->overrideLimits(20);

        $facets->addField('author_facet');

        $fields = $facets->getFields();

        $this->assertArrayHasKey('author_facet', $fields);

        $authorField = $fields['author_facet'];

        $this->assertEquals(20, $authorField->getLimit());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid limits for overriding configuration
     */
    public function testOverrideLimitsInvalidArgument()
    {
        $facets = Set::create();

        $facets->overrideLimits('all');
    }

    public function testAddFieldSettingSorting()
    {
        \Zend_Registry::set('Zend_Config', \Zend_Registry::get('Zend_Config')->merge(new \Zend_Config([
            'searchengine' => [ 'solr' => [ 'sortcrit' => [ 'institute' => 'lexi' ]]]
        ])));

        $facets = Set::create();

        $instituteField = $facets->addField('institute');

        $this->assertEquals('institute', $instituteField->getName());

        $this->assertNotNull($instituteField->getSort());
        $this->assertTrue($instituteField->getSort());
    }

    public function testGetFacetLimitsFromInput()
    {
        $limits = Set::getFacetLimitsFromInput([]);
        $this->assertNull($limits);

        $limits = Set::getFacetLimitsFromInput([
            'facetNumber_year' => 'all',
            'facetNumber_doctype' => 'all'
        ]);
        $this->assertEquals([
            'year' => 10000,
            'doctype' => 10000
        ], $limits);
    }

    public function testGetFacetLimitsFromInputYearInverted()
    {
        $limits = Set::getFacetLimitsFromInput([
            'facetNumber_year' => 'all',
        ]);
        $this->assertEquals([
            'year' => 10000,
        ], $limits);

        \Zend_Registry::get('Zend_Config')->merge(new \Zend_Config([
            'searchengine' => ['solr' => ['facets' => 'doctype,year_inverted']]
        ]));

        $limits = Set::getFacetLimitsFromInput([
            'facetNumber_year' => 'all',
        ]);
        $this->assertEquals([
            'year' => 10000,
        ], $limits);
    }

    public function testGetFacetLimitFromInputForEnrichment()
    {
        $limits = Set::getFacetLimitsFromInput([
            'facetNumber_enrichment_Country' => 'all'
        ]);
        $this->assertNotNull($limits);
        $this->assertEquals(['enrichment_Country' => 10000], $limits);
    }

    public function testSetFieldsWithEnrichmentFacetUsingDot()
    {
        $facets = Set::create();

        $facets->setFields(['author_facet', 'year', 'enrichment_opus.source']);

        $fields = $facets->getFields();

        $this->assertCount(3, $fields);
        $this->assertArrayHasKey('author_facet', $fields);
        $this->assertArrayHasKey('published_year', $fields);
        $this->assertArrayHasKey('enrichment_opus.source', $fields);
    }
}
