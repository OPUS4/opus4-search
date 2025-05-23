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

use Opus\Search\Console\IndexCommand;
use OpusTest\Search\TestAsset\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandTester;

class IndexCommandTest extends TestCase
{
    /**
     * @return array[] option string, block size
     *
     * TODO add more test cases (longform as well)
     */
    public static function blockSizeOptionProvider()
    {
        return [
            [null, 10],
            ['1', 1],
            ['=5', 5],
        ];
    }

    /**
     * @param mixed $option
     * @param int   $blockSize
     * @dataProvider blockSizeOptionProvider
     */
    public function testBlockSizeOption($option, $blockSize)
    {
        $command = new IndexCommand();

        $input = [];

        if ($option !== null) {
            $input['--blocksize'] = $option;
        }

        $tester = new CommandTester($command);
        $tester->execute($input);

        $ref = new ReflectionClass(IndexCommand::class);

        $refBlockSize = $ref->getProperty('blockSize');
        $refBlockSize->setAccessible(true);

        $this->assertEquals($blockSize, $refBlockSize->getValue($command));
    }

    /**
     * @return string[][]
     */
    public static function invalidBlockSizeOptionProvider()
    {
        return [
            ['a'],
            ['0'],
            ['-1'],
        ];
    }

    /**
     * @param mixed $value
     * @dataProvider invalidBlockSizeOptionProvider
     */
    public function testInvalidBlockSizeOption($value)
    {
        $command = new IndexCommand();

        $tester = new CommandTester($command);

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Blocksize must be an integer >= 1');

        $tester->execute([
            '--blocksize' => $value,
        ]);
    }

    public function testIndexing()
    {
        $this->markTestIncomplete();
    }

    public function testRemoveOption()
    {
        $this->markTestIncomplete();
    }
}
