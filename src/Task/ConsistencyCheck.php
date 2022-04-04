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
 * @copyright   Copyright (c) 2013-2022, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Task;

use Opus\Common\Config;
use Opus\Common\Log;
use Opus\Job;
use Opus\Job\Worker\AbstractWorker;
use Opus\Job\Worker\InvalidJobException;
use Opus\Search\Util\ConsistencyCheck as SearchConsistencyCheck;
use Zend_Log;
use Zend_Log_Exception;
use Zend_Log_Formatter_Simple;
use Zend_Log_Writer_Stream;

use function file_exists;
use function fopen;
use function touch;
use function trim;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

/**
 * Worker class for checking consistency between documents in database and Solr index.
 */
class ConsistencyCheck extends AbstractWorker
{
    const LABEL = 'opus-consistency-check';

    private $logfilePath;

    public function __construct()
    {
        $config = Config::get();
        if (isset($config->workspacePath) && trim($config->workspacePath) !== '') {
            $this->logfilePath = $config->workspacePath . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'opus_consistency-check.log';
        }
        $this->setLogger();
    }

    /**
     * Return message label that is used to trigger worker process.
     *
     * @return string Message label.
     */
    public function getActivationLabel()
    {
        return self::LABEL;
    }

    /**
     * Load all published documents from database and check consistency.
     * A document is considered as inconsistent, if
     *
     * - it exists in database, but does not exist in Solr index
     * - it exists in Solr index, but does not exist in database or exists
     *   but with server_state != published
     * - it exists both in database and Solr index, but server_date_modified
     *   timestamps do not coincide
     *
     * @param Job $job Job description and attached data.
     */
    public function work(Job $job)
    {
        // make sure we have the right job
        if ($job->getLabel() !== $this->getActivationLabel()) {
            throw new InvalidJobException($job->getLabel() . " is not a suitable job for this worker.");
        }

        $lockFile = $this->logfilePath . '.lock';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        touch($lockFile);
        $consistencyChecker = new SearchConsistencyCheck($this->logger);
        $consistencyChecker->run();
        unlink($lockFile);
    }

    /**
     * @param Log|null $logger
     * @throws Zend_Log_Exception
     */
    public function setLogger($logger = null)
    {
        if ($this->logfilePath !== null) {
            $logfile = @fopen($this->logfilePath, 'w', false);
            $writer  = new Zend_Log_Writer_Stream($logfile);

            $format    = '[%timestamp%] %priorityName%: %message%' . PHP_EOL;
            $formatter = new Zend_Log_Formatter_Simple($format);
            $writer->setFormatter($formatter);

            parent::setLogger(new Zend_Log($writer));
        } else {
            parent::setLogger(null);
        }
    }
}
