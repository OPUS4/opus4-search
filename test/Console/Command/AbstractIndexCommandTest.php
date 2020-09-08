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
 * @copyright   Copyright (c) 2020, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Console\Command;

use OpusTest\Search\TestAsset\TestCase;

class AbstractIndexCommandTest extends TestCase
{

    /**
     * @return array[] Arguments string, startId, endId, singleDocument
     */
    public function argumentsProvider()
    {
        return [
            ['', null, null, false],
            ['10', 10, null, true],
            ['10 20', 10, 20, false],
            ['-10', null, 10, false],
            ['10-', 10, null, false],
            ['10-25', 10, 25, false],
            ['45 -', 45, null, false],
            ['- 80', null, 80, false]
        ];
    }

    /**
     * @param $arguments
     * @param $startId
     * @param $endId
     * @param $singleDocument
     *
     * @dataProvider argumentsProvider
     */
    public function testDocumentRangeArguments($arguments, $startId, $endId, $singleDocument)
    {
        // TODO need to access protected variables
        $this->markTestIncomplete('IMPORTANT tricky code');
    }
}
