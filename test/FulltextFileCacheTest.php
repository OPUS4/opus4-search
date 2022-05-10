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
 * @copyright   Copyright (c) 2009-2017, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search;

use Opus\Document;
use Opus\File;
use Opus\Search\FulltextFileCache;
use OpusTest\Search\TestAsset\TestCase;

use function file_exists;

use const DIRECTORY_SEPARATOR;

class FulltextFileCacheTest extends TestCase
{
    public function tearDown()
    {
        $this->clearFiles();

        parent::tearDown();
    }

    public function testGetCacheFileName()
    {
        $doc = Document::new();
        $doc->setServerState('published');

        $fulltextDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'TestAsset'
        . DIRECTORY_SEPARATOR . 'fulltexts' . DIRECTORY_SEPARATOR;

        $fileName = 'test.pdf';

        $file = $doc->addFile();
        $file->setTempFile($fulltextDir . $fileName);
        $file->setPathName($fileName);
        $file->setLabel($fileName);
        $file->setVisibleInFrontdoor('1');
        $doc->store();

        $name = FulltextFileCache::getCacheFileName($file);

        $this->assertContains(
            'solr_cache---1ba50dc8abc619cea3ba39f77c75c0fe'
            . '-f87dffb1d8f33844154e214711674407e2493e6188b1411481e6a38fe071064e.txt',
            $name
        );

        $file2 = new File($file->getId());

        $name2 = FulltextFileCache::getCacheFileName($file2);

        $this->assertEquals($name, $name2);

        $this->assertTrue(file_exists($name2));
    }
}
