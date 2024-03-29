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
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Task;

use Opus\Common\Config;
use Opus\Common\Document;
use Opus\Common\Job;
use Opus\Job\InvalidJobException;
use Opus\Search\Task\ConsistencyCheck;
use OpusTest\Search\TestAsset\TestCase;

use function file_get_contents;

use const DIRECTORY_SEPARATOR;

class ConsistencyCheckTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->job    = Job::new();
        $this->worker = new ConsistencyCheck();
    }

    public function testActivationLabel()
    {
        $this->assertEquals(ConsistencyCheck::LABEL, $this->worker->getActivationLabel());
    }

    public function testInvalidJobExecution()
    {
        $this->job->setLabel('invalid-label');
        $this->expectException(InvalidJobException::class);
        $this->worker->work($this->job);
    }

    public function testValidJobExecution()
    {
        // create a published test doc
        $doc = Document::new();
        $doc->setServerState('published');
        $doc->store();

        $this->job->setLabel(ConsistencyCheck::LABEL);
        $this->worker->work($this->job);

        // check if consistency check log file was created and is not empty
        $config      = Config::get();
        $logfilePath = $config->workspacePath . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR
        . 'opus_consistency-check.log';
        $this->assertFileExists($logfilePath);

        $content = file_get_contents($logfilePath);
        $this->assertStringContainsString('checking 1 published documents for consistency.', $content);
        $this->assertStringContainsString('No inconsistency was detected.', $content);
        $this->assertStringContainsString('Completed operation after ', $content);
    }
}
