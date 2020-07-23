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
 * @author      Thomas Urban <thomas.urban@cepharum.de>
 * @copyright   Copyright (c) 2009-2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Facet;

use Opus\Search\Config;

/**
 * This class implements API for generically work with search engines supporting
 * faceted search. This class is used for describing elements of a query
 * affecting faceted search.
 *
 * @note _This class is included for keeping new search engine adapter downward
 *       compatible regarding integration with existing code of Opus4._ All
 *       further options regarding faceted search might be used with improved
 *       support for configuration-based definition of search queries.
 *       @see Opus_Search_Searchable::namedSearch()
 */

class Set
{

    const GLOBABL_KEY = '__global__';

    const LIMIT_KEY = 'limit';

    const SORT_KEY = 'sort';

    protected $name = 'default';

    /**
     * Default configuration for fields including limits and sorting.
     * @var array
     */
    protected $config = [];

    protected $fields = [];

    protected $facetOnly = false;

    /**
     * @param string $facetSetName name of current set of facets
     * @param string $serviceDomain name of search engine domain used for selecting proper configuration
     * @throws Zend_Config_Exception
     */
    protected function __construct($facetSetName = 'default', $serviceDomain = null)
    {
        if (! is_string($facetSetName) || ! ( $facetSetName = trim($facetSetName) )) {
            throw new \InvalidArgumentException('invalid facet set name');
        }

        $this->config[self::LIMIT_KEY] = Config::getFacetLimits($facetSetName, $serviceDomain);
        $this->config[self::SORT_KEY] = Config::getFacetSorting($facetSetName, $serviceDomain);

        $this->name = $facetSetName;
    }

    /**
     * Creates instance of facet-related information to provide in a query for
     * for extending it.
     *
     * @see Opus_Search_Query::setFacet()
     * @return Opus_Search_Facet_Set
     */
    public static function create($facetSetName = 'default', $serviceDomain = null)
    {
        return new static( $facetSetName, $serviceDomain );
    }

    /**
     * Provides set of limits for overriding configured ones.
     *
     * @note Overridden limits are used in succeeding calls for adding fields to
     *       current set. Any previously added field isn't adjusted implicitly
     *       and thus needs to be modified individually.
     *
     * @param int[]|int $limits map of field names into limits or single global limit
     * @return $this fluent interface
     */
    public function overrideLimits($limits)
    {
        if (is_array($limits)) {
            $config = \Opus_Config::get();

            $mappedLimits = [];

            // TODO cleanup, centralize mapping, so it does not have to happen in various places
            foreach ($limits as $name => $value) {
                if (isset($config->search->facet->$name->indexField)) {
                    $mappedLimits[$config->search->facet->$name->indexField] = $value;
                } else {
                    $mappedLimits[$name] = $value;
                }
            };

            // replace field-specific limits but keep previously cached global
            // limit unless provided set is overriding that as well.
            $this->config[self::LIMIT_KEY] = array_replace($this->config[self::LIMIT_KEY], $mappedLimits);
        } elseif (preg_match('/^[+-]?\d+$/', $limits)) {
            // got single integer ... reset limits to use given one globally, only
            $this->config[self::LIMIT_KEY] = [ self::GLOBABL_KEY => intval($limits) ];
        } else {
            throw new \InvalidArgumentException('invalid limits for overriding configuration');
        }

        return $this;
    }

    /**
     * Retrieves name of current facet set.
     *
     * @return string
     */
    public function getSetName()
    {
        return $this->name;
    }

    /**
     * Adds another facet field to current facet set.
     *
     * @param string $name name of field to add (existing field is returned if it's been added before)
     * @return Opus_Search_Facet_Field description of added or already existing field
     */
    public function addField($name)
    {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        }

        $field = Field::create($name)->setMinCount(1);

        if (array_key_exists($name, $this->config[self::SORT_KEY])) {
            $field->setSort($this->config[self::SORT_KEY][$name]);
        }

        if (array_key_exists($name, $this->config[self::LIMIT_KEY])) {
            $field->setLimit($this->config[self::LIMIT_KEY][$name]);
        } elseif (array_key_exists(self::GLOBABL_KEY, $this->config[self::LIMIT_KEY])) {
            $field->setLimit($this->config[self::LIMIT_KEY][self::GLOBABL_KEY]);
        } else {
            $field->setLimit(10);
        }

        $this->fields[$name] = $field;

        return $field;
    }

    /**
     * @return Field[] named map of facet fields
     */
    public function getFields()
    {
        return $this->fields;
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
            $input = [ $input ];
        }

        $output = [];

        foreach ($input as $field) {
            if (! is_string($field)) {
                throw new \InvalidArgumentException('invalid type of field selector');
            }

            $fieldNames = preg_split('/[\s,]+/', $field, null, PREG_SPLIT_NO_EMPTY);
            foreach ($fieldNames as $name) {
                if (! preg_match('/^[a-z_][a-z0-9_\.]*$/i', $name)) {
                    throw new \InvalidArgumentException('malformed field selector: ' . $name);
                }

                $output[] = $name;
            }
        }

        if (! count($input)) {
            throw new \InvalidArgumentException('missing field selector');
        }

        return $output;
    }

    /**
     * Declares (another) set of fields to obey in faceted search.
     *
     * @param string[]|string $fieldNames set of (optionally comma-separated lists of) field names
     * @param bool $adding true for adding given fields to previously declared fields instead of replacing those
     * @return $this fluent interface
     */
    public function setFields($fieldNames, $adding = false)
    {
        if (! $adding) {
            $this->fields = [];
        }

        $config = \Opus_Config::get();

        foreach ($this->normalizeFields($fieldNames) as $name) {
            $indexField = $name;

            if (isset($config->search->facet->$name->indexField)) {
                $indexField = $config->search->facet->$name->indexField;
            }

            $this->addField($indexField);
        }

        return $this;
    }

    /**
     * Normalizes set of fields for faceted search according to contained input
     * parameters of script.
     *
     * @note This method is replacing functionality of application's
     *       Solrsearch_Model_FacetMenu::buildFacetArray() for containing its
     *       feature in Opus_Search package more closely and for slightly
     *       revising it by supporting provision of limit to apply per switch.
     *
     * @param array $input
     * @param int $limit limit to set on every given field in input
     * @return array|null
     */
    public static function getFacetLimitsFromInput($input, $limit = 10000)
    {
        $limit = intval($limit);

        $limits = [];

        $prefix = 'facetNumber_';
        $prefixLength = strlen($prefix);

        foreach ($input as $key => $value) {
            if (substr($key, 0, $prefixLength) === $prefix) {
                $facet = substr($key, $prefixLength);
                $limits[$facet] = $limit;
            }
        }

        return count($limits) ? $limits : null;
    }

    /**
     * Limits search query to do faceted search, only.
     *
     * @return $this fluent interface
     */
    public function setFacetOnly()
    {
        $this->facetOnly = true;

        return $this;
    }

    /**
     * Indicates if search is limited to faceted search, only, or not.
     *
     * @return bool true if search is limited, false otherwise
     */
    public function isFacetOnly()
    {
        return $this->facetOnly;
    }
}
