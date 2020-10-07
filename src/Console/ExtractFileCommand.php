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
 * @category    Application
 * @package     Application_Console
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2020, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Console;

use Opus\Search\Console\Helper\IndexHelper;
use Opus\Search\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Application_Console_Index_ExtractFileCommand
 *
 * TODO (handle not supported format message) check if mime type is supported?
 * TODO tests
 * TODO add timeout option
 */
class ExtractFileCommand extends Command
{

    const ARGUMENT_FILE = 'File';

    const OPTION_OUTPUT_FILE = 'Output';

    protected static $defaultName = 'tools:extract-file';

    protected function configure()
    {
        $help = <<<EOT
The <fg=green>tools:extract-file</> command can be used to perform the extraction of
a single file provided as argument. This can be used for testing the extraction of a
file. 
EOT;

        $this->setName(self::$defaultName)
            ->setDescription('Extracts text from a file')
            ->setHelp($help)
            ->addArgument(
                self::ARGUMENT_FILE,
                InputArgument::REQUIRED,
                'File for extraction'
            )->addOption(
                self::OPTION_OUTPUT_FILE,
                'o',
                InputOption::VALUE_REQUIRED,
                'Write extraction output to file'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument(self::ARGUMENT_FILE);
        $target = $input->getOption(self::OPTION_OUTPUT_FILE);

        // TODO check if target exists and verify overwriting?

        $helper = new IndexHelper();
        $helper->setOutput($output);

        try {
            $text = $helper->extractFile($file);
        } catch (\Exception $e) {
            $output->write($e);
            throw new Exception($e->getMessage());
        }

        if ($target !== null) {
            file_put_contents($target, $text);
        } else {
            $output->writeln($text);
        }
    }
}
