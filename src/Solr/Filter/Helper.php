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
 * @copyright   Copyright (c) 2009, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Solr\Filter;

use function explode;
use function preg_replace;
use function strpos;
use function strtolower;
use function substr_count;
use function trim;

class Helper
{
    /**
     * @param string $term
     * @return string
     */
    public static function escapePhrase($term)
    {
        $term = trim($term);

        // add one " to the end of $query if it contains an odd number of "
        if (substr_count($term, '"') % 2 === 1) {
            $term .= '"';
        }

        // escape special characters (currently ignore " \* \?) outside of ""
        $insidePhrase = false;
        $result       = '';

        foreach (explode('"', $term) as $phrase) {
            if ($insidePhrase) {
                $result .= '"' . $phrase . '"';
            } else {
                $phrase  = static::lowercaseLiterals($phrase);
                $result .= preg_replace(
                    '/(\s+|\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^|~|:|\\\|\/)/',
                    '\\\$1',
                    $phrase
                );
            }

            $insidePhrase = ! $insidePhrase;
        }

        return $result;
    }

    /**
     * @param string $query
     * @return string
     */
    protected static function lowercaseLiterals($query)
    {
        // check if $query is a wildcard query
        if (strpos($query, '*') === false && strpos($query, '?') === false) {
            return $query;
        }

        // lowercase query
        return strtolower($query);
    }
}
