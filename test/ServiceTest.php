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
 * @category    Test
 * @author      Thomas Urban <thomas.urban@cepharum.de>
 * @copyright   Copyright (c) 2009-2019, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search;

use Opus\Search\Service;
use OpusTest\Search\TestAsset\TestCase;

class ServiceTest extends TestCase
{

    public function testProvidesIndexService()
    {
        $service = Service::selectIndexingService(null, 'solr');

        $this->assertInstanceOf('Opus\Search\Indexing', $service);
        $this->assertInstanceOf('Opus\Search\Solr\Solarium\Adapter', $service);
    }

    public function testProvidesExtractService()
    {
        $service = Service::selectExtractingService(null, 'solr');

        $this->assertInstanceOf('Opus\Search\Extracting', $service);
        $this->assertInstanceOf('Opus\Search\Solr\Solarium\Adapter', $service);
    }

    public function testProvidesSearchService()
    {
        $service = Service::selectSearchingService(null, 'solr');

        $this->assertInstanceOf('Opus\Search\Searching', $service);
        $this->assertInstanceOf('Opus\Search\Solr\Solarium\Adapter', $service);
    }

    public function testCachingService()
    {
        $searchA = Service::selectSearchingService(null, 'solr');
        $searchB = Service::selectSearchingService(null, 'solr');

        $this->assertTrue($searchA === $searchB);

        Service::dropCached();

        $searchC = Service::selectSearchingService(null, 'solr');

        $this->assertTrue($searchA === $searchB);
        $this->assertTrue($searchA !== $searchC);
    }

    public function testGetQualifiedDomain()
    {
        $domain = Service::getQualifiedDomain();

        $this->assertEquals('solr', $domain);

        $this->adjustConfiguration([
            'searchengine' => ['domain' => 'elastic']
        ]);

        $domain = Service::getQualifiedDomain();

        $this->assertEquals('elastic', $domain);

        $domain = Service::getQualifiedDomain('mysql');

        $this->assertEquals('mysql', $domain);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid default search domain
     */
    public function testGetQualifiedDomainInvalidDomainNotAString()
    {
        $domain = Service::getQualifiedDomain(10);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid default search domain
     */
    public function testGetQualifiedDomainInvalidDomainEmpty()
    {
        $domain = Service::getQualifiedDomain('   ');
    }
}
