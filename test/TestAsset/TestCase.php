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
 * @author      Thoralf Klein <thoralf.klein@zib.de>
 * @copyright   Copyright (c) 2008-2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

namespace OpusTest\Search\TestAsset;

use Opus\Search\Config;
use Opus\Search\Service;
use Opus\Db\Util\DatabaseHelper;

/**
 * Superclass for all tests.  Providing maintainance tasks.
 *
 * @category Tests
 */
class TestCase extends SimpleTestCase
{

    protected function clearDatabase()
    {
        $databaseHelper = new DatabaseHelper();
        $databaseHelper->clearTables();
    }

    /**
     * Removes all documents from Solr index.
     */
    protected function clearSolrIndex()
    {
        Service::selectIndexingService(null, 'solr')->removeAllDocumentsFromIndex();
    }

    /**
     * Deletes folders in workspace/files in case a test didn't do proper cleanup.
     * @param null $directory
     */
    protected function clearFiles($directory = null)
    {
        if (is_null($directory)) {
            if (empty(APPLICATION_PATH)) {
                return;
            }
            $filesDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'workspace'
                . DIRECTORY_SEPARATOR . 'files';
            $files = array_diff(scandir($filesDir), ['.', '..', '.gitignore']);
        } else {
            $filesDir = $directory;
            $files = array_diff(scandir($filesDir), ['.', '..']);
        }

        foreach ($files as $file) {
            $path = $filesDir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->clearFiles($path);
            } else {
                unlink($path);
            }
        }

        if (! is_null($directory)) {
            rmdir($directory);
        }

        return;
    }

    /**
     * Standard setUp method for clearing database.
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();

        Config::dropCached();
        Service::dropCached();

        $this->clearDatabase();
        $this->clearSolrIndex();
    }

    protected function tearDown()
    {
        $this->clearSolrIndex();

        parent::tearDown();
    }
}
