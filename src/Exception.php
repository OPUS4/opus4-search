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
 * @copyright   Copyright (c) 2009-2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search;

use Exception as PhpException;

/**
 * Implements common exception to be used in code of search engine adapters.
 *
 * TODO code duplication in extending classes
 * TODO rename to SearchException
 */
class Exception extends PhpException
{
    const SERVER_UNREACHABLE = '1';

    const INVALID_QUERY = '2';

    /**
     * @param string      $message
     * @param int|null    $code
     * @param parent|null $previous
     */
    public function __construct($message, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return bool
     */
    public function isServerUnreachable()
    {
        return $this->code === self::SERVER_UNREACHABLE;
    }

    /**
     * @return bool
     */
    public function isInvalidQuery()
    {
        return $this->code === self::INVALID_QUERY;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $previousMessage = '';
        if ($this->getPrevious() !== null) {
            $previousMessage = $this->getPrevious()->getMessage();
        }

        if ($this->isServerUnreachable()) {
            return "solr server is unreachable: $previousMessage";
        }

        if ($this->isInvalidQuery()) {
            return "given search query is invalid: $previousMessage";
        }

        return 'unknown error while trying to search: ' . $previousMessage;
    }
}
