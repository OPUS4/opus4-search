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
 * @copyright   Copyright (c) 2020, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Console;

use Opus\Search\Console\ExtractFileCommand;
use Opus\Search\SearchException;
use OpusTest\Search\TestAsset\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function trim;

class ExtractFileCommandTest extends TestCase
{
    public function testExtractFile()
    {
        $command = new ExtractFileCommand();

        $tester = new CommandTester($command);

        $tester->execute([
            'file' => APPLICATION_PATH . '/test/TestAsset/fulltexts/test.pdf',
        ]);

        $expected = <<<EOT
Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut 
labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et 
ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum 
dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore 
magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet 
clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
EOT;

        $this->assertEquals($expected, trim($tester->getDisplay()));
    }

    public function testTargetOption()
    {
        $this->markTestIncomplete('waiting for OPUSVIER-4400');

        $command = new ExtractFileCommand();

        $tester = new CommandTester($command);

        $tempFile = '';

        /*
        $tester->execute([
        '--output' => $tempFile,
        'file' => APPLICATION_PATH . '/test/TestAsset/fulltexts/test.pdf'
        ]);*/
    }

    public function testFileTypeNotSupported()
    {
        $this->markTestIncomplete();
    }

    public function testInvalidFile()
    {
        $command = new ExtractFileCommand();

        $tester = new CommandTester($command);

        $this->expectException(SearchException::class);
        $this->expectExceptionMessage('failed extracting fulltext data');

        $tester->execute([
            'file' => APPLICATION_PATH . '/test/TestAsset/fulltexts/test-invalid.pdf',
        ]);
    }

    public function testMoreTestsForSupportedFileTypes()
    {
        $this->markTestIncomplete('placeholder for additional tests');
    }
}
