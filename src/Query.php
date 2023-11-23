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

namespace Opus\Search;

use InvalidArgumentException;
use Opus\Common\Config;
use Opus\Search\Config as SearchConfig;
use Opus\Search\Facet\Set;
use Opus\Search\Filter\AbstractFilterBase;
use RuntimeException;
use Zend_Config;

use function array_key_exists;
use function array_merge;
use function array_shift;
use function array_unique;
use function boolval;
use function count;
use function ctype_digit;
use function intval;
use function is_array;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_split;
use function strcasecmp;
use function strtolower;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Implements API for describing search queries.
 *
 * @note This part of Opus search API differs from Solr in terminology in that
 *       all requests for searching documents are considered "queries" with a
 *       "filter" used to describe conditions matching documents has to met.
 *       In opposition to Solr's "filter queries" this API supports "subfilters"
 *       to reduce confusions on differences between filters, queries and
 *       filter queries. Thus wording is mapped like this
 *
 *       Solr                   -->    Opus
 *       "request"              -->    "query"
 *       "query"                -->    "filter"
 *       "filter query"         -->    "subfilter"
 * @method int getStart( int $default = null )
 * @method int getRows( int $default = null )
 * @method string[] getFields( array $default = null )
 * @method array getSort( array $default = null )
 * @method bool getUnion( bool $default = false )
 * @method bool getWeightedSearch( bool $default = false )
 * @method array getWeightedFields( int[] $default = null )
 * @method AbstractFilterBase getFilter(AbstractFilterBase $default = null ) retrieves condition to be met by resulting documents
 * @method Set getFacet( Set $default = null )
 * @method $this setStart( int $offset )
 * @method $this setRows( int $count )
 * @method $this setFields( $fields )
 * @method $this setSort( $sorting )
 * @method $this setUnion( bool $isUnion )
 * @method $this setFilter(AbstractFilterBase $filter ) assigns condition to be met by resulting documents
 * @method $this setFacet( Set $facet )
 * @method $this addFields( string $fields )
 * @method $this addSort( $sorting )
 * @method $this setWeightedSearch( bool $isWeightedSearch )
 * @method $this setWeightedFields( int[] $weightedFields ) assigns boost factors to fields (e.g. [ 'title' => 10, 'abstract' => 0.5 ])
 */
class Query
{
    /** @var array */
    protected $data;

    public function reset()
    {
        $this->data = [
            'start'          => null,
            'rows'           => null,
            'fields'         => null,
            'sort'           => null,
            'union'          => false,
            'filter'         => null,
            'facet'          => null,
            'subfilters'     => null,
            'weightedsearch' => null,
            'weightedfields' => null,
        ];
    }

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Tests if provided name is actually name of known parameter normalizing it
     * on return.
     *
     * @param string $name name of parameter to access
     * @return string normalized name of existing parameter
     * @throws InvalidArgumentException Unless providing name of existing parameter.
     */
    protected function isValidParameter($name)
    {
        if (! array_key_exists(strtolower(trim($name)), $this->data)) {
            throw new InvalidArgumentException('invalid query parameter: ' . $name);
        }

        return strtolower(trim($name));
    }

    /**
     * Normalizes one or more field names or set of comma-separated field names
     * into set of field names.
     *
     * @param string|string[] $input one or more field names or comma-separated lists of fields' names
     * @return string[] list of field names
     */
    protected function normalizeFields($input)
    {
        if (! is_array($input)) {
            $input = [$input];
        }

        $output = [];

        foreach ($input as $field) {
            if (! is_string($field)) {
                throw new InvalidArgumentException('invalid type of field selector');
            }

            $fieldNames = preg_split('/[\s,]+/', $field, 0, PREG_SPLIT_NO_EMPTY);
            foreach ($fieldNames as $name) {
                if (! preg_match('/^(?:\*|[a-z_][a-z0-9_.]*)$/i', $name)) {
                    throw new InvalidArgumentException('malformed field selector: ' . $name);
                }

                $output[] = $name;
            }
        }

        if (! count($input)) {
            throw new InvalidArgumentException('missing field selector');
        }

        return $output;
    }

    /**
     * Parses provided parameter for describing some sorting direction.
     *
     * @param string|bool $ascending one out of true, false, "asc" or "desc"
     * @return bool true if parameter is considered requesting to sort in ascending order
     */
    protected function normalizeDirection($ascending)
    {
        if (! strcasecmp($ascending, 'asc')) {
            $ascending = true;
        } elseif (! strcasecmp($ascending, 'desc')) {
            $ascending = false;
        } elseif ($ascending !== false && $ascending !== true) {
            throw new InvalidArgumentException('invalid sorting direction selector');
        }

        return $ascending;
    }

    /**
     * Retrieves value of selected query parameter.
     *
     * @param string     $name name of parameter to read
     * @param null|mixed $defaultValue value to retrieve if parameter hasn't been set internally
     * @return mixed value of selected parameter, default if missing internally
     */
    public function get($name, $defaultValue = null)
    {
        $name = $this->isValidParameter($name);

        $config = Config::get();

        // prefer config options over getter defaults
        switch ($name) {
            case 'weightedsearch':
                $defaultValue = isset($config->search->weightedSearch) ? boolval($config->search->weightedSearch) : $defaultValue;
                break;

            case 'weightedfields':
                $defaultValue = isset($config->search->simple) ? $config->search->simple->toArray() : $defaultValue;
                break;
        }

        return $this->data[$name] ?? $defaultValue;
    }

    /**
     * Sets value of selected query parameter.
     *
     * @param string                    $name name of query parameter to adjust
     * @param string[]|array|string|int $value value of query parameter to write
     * @param bool                      $adding true for adding given parameter to any existing one
     * @return $this
     * @throws InvalidArgumentException In case of invalid arguments (e.g. on trying to add value to single-value parameter).
     */
    public function set($name, $value, $adding = false)
    {
        $name = $this->isValidParameter($name);

        switch ($name) {
            case 'start':
            case 'rows':
                if ($adding) {
                    throw new InvalidArgumentException('invalid parameter access on ' . $name);
                }

                if (! is_scalar($value) || ! ctype_digit(trim($value))) {
                    throw new InvalidArgumentException('invalid parameter value on ' . $name);
                }

                $this->data[$name] = intval($value);
                break;

            case 'fields':
                $fields = $this->normalizeFields($value);

                if ($adding && $this->data['fields'] === null) {
                    $adding = false;
                }

                if ($adding) {
                    $this->data['fields'] = array_merge($this->data['fields'], $fields);
                } else {
                    if (! count($fields)) {
                        throw new InvalidArgumentException('setting empty set of fields rejected');
                    }

                    $this->data['fields'] = $fields;
                }

                $this->data['fields'] = array_unique($this->data['fields']);
                break;

            case 'sort':
                if (! is_array($value)) {
                    $value = [$value, true];
                }

                switch (count($value)) {
                    case 2:
                        $fields    = array_shift($value);
                        $ascending = array_shift($value);
                        break;
                    case 1:
                        $fields    = array_shift($value);
                        $ascending = true;
                        break;
                    default:
                        throw new InvalidArgumentException('invalid sorting selector');
                }

                $this->addSorting($fields, $ascending, ! $adding);
                break;

            case 'union':
            case 'weightedsearch':
                if ($adding) {
                    throw new InvalidArgumentException('invalid parameter access on ' . $name);
                }

                $this->data[$name] = ! ! $value;
                break;

            case 'filter':
                if ($adding) {
                    throw new InvalidArgumentException('invalid parameter access on ' . $name);
                }

                if (! $value instanceof AbstractFilterBase) {
                    throw new InvalidArgumentException('invalid filter');
                }

                $this->data[$name] = $value;
                break;

            case 'facet':
                if ($adding) {
                    throw new InvalidArgumentException('invalid parameter access on ' . $name);
                }

                if (! $value instanceof Set) {
                    throw new InvalidArgumentException('invalid facet options');
                }

                $this->data[$name] = $value;
                break;

            case 'subfilters':
                throw new RuntimeException('invalid access on sub filters');

            case 'weightedfields':
                if ($adding) {
                    throw new InvalidArgumentException('invalid parameter access on ' . $name);
                }

                if (! is_array($value)) {
                    throw new InvalidArgumentException('invalid query fields option');
                }

                $this->data[$name] = $value;
                break;
        }

        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->get($name) !== null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $this->set($name, $value, false);
    }

    /**
     * @param string $method
     * @param array  $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (preg_match('/^(get|set|add)([a-z]+)$/i', $method, $matches)) {
            $property = $this->isValidParameter($matches[2]);
            switch (strtolower($matches[1])) {
                case 'get':
                    return $this->get($property, @$arguments[0]);

                case 'set':
                    $this->set($property, @$arguments[0], false);
                    return $this;

                case 'add':
                    $this->set($property, @$arguments[0], true);
                    return $this;
            }
        }

        throw new RuntimeException('invalid method: ' . $method);
    }

    /**
     * Adds request for sorting by some field in desired order.
     *
     * @param string|string[] $field one or more field names to add sorting (as array and/or comma-separated string)
     * @param bool            $ascending true or "asc" for ascending by all given fields
     * @param bool            $reset true for dropping previously declared sorting
     * @return $this fluent interface
     */
    public function addSorting($field, $ascending = true, $reset = false)
    {
        $fields    = $this->normalizeFields($field);
        $ascending = $this->normalizeDirection($ascending);

        if (! count($fields)) {
            throw new InvalidArgumentException('missing field for sorting result');
        }

        if ($reset || ! is_array($this->data['sort'])) {
            $this->data['sort'] = [];
        }

        foreach ($fields as $field) {
            if ($field === '*') {
                throw new InvalidArgumentException('invalid request for sorting by all fields (*)');
            }

            $this->data['sort'][$field] = $ascending ? 'asc' : 'desc';
        }

        return $this;
    }

    /**
     * Declares some subfilter.
     *
     * @see http://wiki.apache.org/solr/CommonQueryParameters#fq
     *
     * @note In Solr a search includes a "query" and optionally one or more
     *       "filter query". This API intends different terminology for the
     *       whole search request is considered a "query" with a "filter" used
     *       to select actually desired documents by matching conditions. In
     *       context with this terminology "subfilter" was used to describe what
     *       is "filter query" in Solr world: some named query to be included on
     *       selecting documents in database with some benefits regarding
     *       performance, server-side result caching and non-affecting score.
     * @param string             $name name of query (used for server-side caching)
     * @param AbstractFilterBase $subFilter filter to be satisfied by all matching documents in addition
     * @return $this fluent interface
     */
    public function setSubFilter($name, AbstractFilterBase $subFilter)
    {
        if (! is_string($name) || ! $name) {
            throw new InvalidArgumentException('invalid sub filter name');
        }

        if (! is_array($this->data['subfilters'])) {
            $this->data['subfilters'] = [$name => $subFilter];
        } else {
            $this->data['subfilters'][$name] = $subFilter;
        }

        return $this;
    }

    /**
     * Removes some previously defined subfilter from current query again.
     *
     * @see Opus_Search_Query::setSubFilter()
     *
     * @note This isn't affecting server-side caching of selected filter but
     *       reverting some parts of query compiled on client-side.
     * @param string $name name of filter to remove from query again
     * @return $this fluent interface
     */
    public function removeSubFilter($name)
    {
        if (! is_string($name) || ! $name) {
            throw new InvalidArgumentException('invalid sub filter name');
        }

        if (is_array($this->data['subfilters'])) {
            if (array_key_exists($name, $this->data['subfilters'])) {
                unset($this->data['subfilters'][$name]);
            }

            if (! count($this->data['subfilters'])) {
                $this->data['subfilters'] = null;
            }
        }

        return $this;
    }

    /**
     * Retrieves named map of subfilters to include on querying search engine.
     *
     * @return AbstractFilterBase[]
     */
    public function getSubFilters()
    {
        return $this->data['subfilters'];
    }

    /**
     * @param string      $name
     * @param bool        $fallbackIfMissing
     * @param string|null $oldName
     * @return mixed
     */
    public static function getParameterDefault($name, $fallbackIfMissing, $oldName = null)
    {
        $config   = SearchConfig::getDomainConfiguration();
        $defaults = $config->parameterDefaults;

        if ($defaults instanceof Zend_Config) {
            return $defaults->get($name, $fallbackIfMissing);
        }

        if ($oldName) {
            return $config->get($oldName, $fallbackIfMissing);
        }

        return $fallbackIfMissing;
    }

    /**
     * Retrieves configured default offset for paging results.
     *
     * @return int
     */
    public static function getDefaultStart()
    {
        return static::getParameterDefault('start', 0);
    }

    /**
     * Retrieves configured default number of rows to show (per page).
     *
     * @return int
     */
    public static function getDefaultRows()
    {
        return static::getParameterDefault('rows', 10, 'numberOfDefaultSearchResults');
    }

    /**
     * Retrieves configured default sorting.
     *
     * @return string[]
     */
    public static function getDefaultSorting()
    {
        $sorting = static::getParameterDefault('sortField', 'score desc');

        $parts = preg_split('/[\s,]+/', trim($sorting), 0, PREG_SPLIT_NO_EMPTY);

        $sorting = [array_shift($parts)];

        if (! count($parts)) {
            $sorting[] = 'desc';
        } else {
            $dir = array_shift($parts);
            if (strcasecmp($dir, 'asc') || strcasecmp($dir, 'desc')) {
                $dir = 'desc';
            }

            $sorting[] = strtolower($dir);
        }

        return $sorting;
    }

    /**
     * Retrieves configured name of field to use for sorting results by default.
     *
     * @return string
     */
    public static function getDefaultSortingField()
    {
        $sorting = static::getDefaultSorting();
        return $sorting[0];
    }
}
