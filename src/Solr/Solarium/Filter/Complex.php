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
 * @copyright   Copyright (c) 2009-2022, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Solr\Solarium\Filter;

use InvalidArgumentException;
use Opus\Search\Filter\AbstractFilterComplex;
use Opus\Search\Filter\Simple;
use Opus\Search\Filtering;
use Opus\Search\Solr\Filter\Helper;
use Solarium\Client;
use Solarium\Core\Query\AbstractQuery;

use function array_map;
use function count;
use function implode;

class Complex extends AbstractFilterComplex
{
    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Delivers glue for concatenating terms according to given filter's
     * combination of particular result sets.
     *
     * @return string
     */
    protected static function glue(parent $complex)
    {
        return $complex->isRequestingUnion() ? ' OR ' : ' AND ';
    }

    /**
     * Compiles simple condition to proper Solr query term.
     *
     * @return string
     */
    protected static function compileSimple(AbstractQuery $query, Simple $simple)
    {
        // validate desired type of comparison
        switch ($simple->getComparator()) {
            case Simple::COMPARE_EQUALITY:
                $negated = false;
                break;
            case Simple::COMPARE_INEQUALITY:
                $negated = true;
                break;
            default:
                // TODO implement additional types of comparison
                throw new InvalidArgumentException('comparison not supported by Solr adapter');
        }

        // handle range checks
        if ($simple->isRangeValue()) {
            [$lower, $upper] = $simple->getRangeValue();

            return $query->getHelper()->rangeQuery($simple->getName(), $lower, $upper);
        }

        // handle checks for (not) matching phrases
        // (resulting term might be complex in case of testing multiple values)
        $values = $simple->getValues();
        if (! count($values)) {
            throw new InvalidArgumentException('missing values on field ' . $simple->getName());
        } else {
            $name = $simple->getName();
            if ($name === '*' && ( count($values) !== 1 || $values[0] !== '*' )) {
                // special case: simple term requests to match any field
                $name = '';
            } else {
                $name .= ':';
            }

            if ($negated) {
                $name = '-' . $name;
            }

            $values = array_map(function ($value) use ($name) {
                return $name . Helper::escapePhrase($value);
            }, $values);

            if (count($values) === 1) {
                return $values[0];
            }

            return '(' . implode($negated ? ' AND ' : ' OR ', $values) . ')';
        }
    }

    /**
     * Compiles provided set of subordinated conditions into complex Solr query
     * term.
     *
     * @param Filtering[] $conditions
     * @param string      $glue
     * @return string
     */
    protected static function compileQuery(AbstractQuery $query, $conditions, $glue)
    {
        $compiled = [];

        foreach ($conditions as $condition) {
            if ($condition instanceof AbstractFilterComplex) {
                $term = static::compileQuery($query, $condition->getConditions(), static::glue($condition));
                $term = "($term)";
                if ($condition->isGloballyNegated()) {
                    $term = '-' . $term;
                }

                $compiled[] = $term;
            } elseif ($condition instanceof Simple) {
                $compiled[] = static::compileSimple($query, $condition);
            }
        }

        return implode($glue, $compiled);
    }

    /**
     * @param mixed $query
     * @return string|null
     */
    public function compile($query)
    {
        return static::compileQuery($query, $this->getConditions(), static::glue($this));
    }
}
