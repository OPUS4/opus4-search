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

namespace Opus\Search\IndexBuilder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class AbstractIndexCommand
 * @package Opus\Search\IndexBuilder
 *
 * TODO support multiple IDs like '10 20 30-40 51' ? Basically allow array of arguments, each an ID or range
 * TODO support providing document list in file ?
 */
abstract class AbstractIndexCommand extends Command
{
    const ARGUMENT_START_ID = 'StartID';

    const ARGUMENT_END_ID = 'EndID';

    protected $removeAll = false;

    protected $startId;

    protected $endId;

    protected $singleDocument = false;

    protected function configure()
    {
        $this->addArgument(
            self::ARGUMENT_START_ID,
            InputArgument::OPTIONAL,
            'ID of document where indexing should start (or \'-\')'
        )
        ->addArgument(
            self::ARGUMENT_END_ID,
            InputArgument::OPTIONAL,
            'ID of document where indexing should stop (or \'-\')'
        );
    }

    /**
     * @param InputInterface $input
     */
    protected function processArguments(InputInterface $input)
    {
        $startId = $input->getArgument(self::ARGUMENT_START_ID);
        $endId = $input->getArgument(self::ARGUMENT_END_ID);

        // handle accidental inputs like '20-' or '20-30' instead of '20 -' or '20 30'
        if ($startId !== '-') {
            $parts = mbsplit('-', $startId);
            if (count($parts) === 2) {
                $startId = $parts[0];
                $endId = $parts[1];

                if ($endId === '') {
                    $endId = '-'; // otherwise only a single document will be indexed
                }
            }
        }

        if ($startId === '-' || $startId === '' || $startId === null) {
            $startId = null;
        } else {
            // only activate single document indexing if startId is present and no endId
            if ($endId === '' || $endId === null) {
                $this->singleDocument = true;
                $endId = null;
            }
        }

        if ($endId === '-') {
            $endId = null;
        }

        if ($startId !== null && ! ctype_digit($startId)) {
            throw new InvalidArgumentException('StartID needs to be an integer.');
        }

        if ($endId !== null && ! ctype_digit($endId)) {
            throw new InvalidArgumentException('EndID needs to be an integer.');
        }

        if ($startId === null && $endId === null) {
            $this->removeAll = true;
        } else {
            $this->removeAll = false;

            if ($startId !== null && $endId !== null && $startId > $endId) {
                $tmp = $startId;
                $startId = $endId;
                $endId = $tmp;
            }
        }

        $this->startId = $startId;
        $this->endId = $endId;
    }
}
