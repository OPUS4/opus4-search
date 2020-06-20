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

namespace Opus\Search;

/**
 * Provides access on sections of configuration regarding selected domains of
 * searching and/or particular services or queries defined in either domain.
 *
 * All configuration is available through static methods to be globally
 * accessible in code. This API implements some merging of existing
 * configuration to support fallback.
 *
 * TODO resolve deprecated configuration vs new configuration (cleanup)
 * TODO new configuration very complicated for most usage scenarios
 * TODO refactor for facet focus configuration
 * TODO get rid of service domains (just one search configuration) while still supporting usage of multiple
 *      searchengines for redundancy etc.
 *
 * @see https://github.com/soletan/opus4-framework/wiki/Runtime-Configuration
 *
 * @author Thomas Urban <thomas.urban@cepharum.de>
 */
class Config
{

    protected static $configurationsPool = [];

    /**
     * Drops any cached configuration.
     *
     */
    public static function dropCached()
    {
        self::$configurationsPool = [];
    }

    /**
     * Retrieves extract from configuration regarding integration with some
     * search engine.
     *
     * @return \Zend_Config
     */
    public static function getConfiguration()
    {
        return \Opus_Config::get()->searchengine;
    }

    /**
     * Retrieves extract from configuration regarding integration with search
     * engine of selected domain.
     *
     * Search engine domains are distinct parts of configuration enabling
     * support for different types of search engines using different client
     * adapters, e.g. for querying search engine A for indexing results in
     * search engine B. All current use cases doesn't require to choose any
     * particular domain so using default by omitting parameter is okay for now.
     *
     * Domains might be used to work with different Solr XML document formats on
     * indexing, as well, for XSLT file is configured in scope of such a domain.
     * Again this might be handy on migrating from one search engine to another.
     *
     * @param string $serviceDomain name of a search engine's domain
     * @return \Zend_Config
     */
    public static function getDomainConfiguration($serviceDomain = null)
    {
        $serviceDomain = Service::getQualifiedDomain($serviceDomain);

        $config = static::getConfiguration()->get($serviceDomain);
        if (! ( $config instanceof \Zend_Config )) {
            throw new \InvalidArgumentException('invalid search engine domain: ' . $serviceDomain);
        }

        // adopt all basically deprecated non-service-related configuration
        $config = static::mergeWithDeprecatedDomainConfiguration($config);

        return $config;
    }

    /**
     * Retrieves configuration of selected (name and) type of service.
     *
     * Named services may be used to apply different configurations to
     * different parts of application, e.g. by providing different setup for
     * script bulk-indexing documents or for checking consistency or for
     * querying to list matching documents.
     *
     * @note Default service is retrieved if explicitly selected name of service
     *       is missing. This enables code to request any special service by
     *       name without caring for meeting proper configuration.
     *
     * @param string $serviceType one of Opus_Search_Service::SERVICE_TYPE_* constants
     * @param string $serviceName name of service, omit for 'default'
     * @param string $serviceDomain name of domain selected service belongs to
     * @return \Zend_Config
     */
    public static function getServiceConfiguration($serviceType, $serviceName = null, $serviceDomain = null)
    {
        if (! $serviceName || ! is_string($serviceName)) {
            $serviceName = 'default';
        }

        // try runtime cache first to keep configurations from being re-merged
        $hash = sha1("$serviceDomain::$serviceName::$serviceType");
        if (array_key_exists($hash, self::$configurationsPool)) {
            return self::$configurationsPool[$hash];
        }


        // get domain configuration (e.g. all options with prefix searchengine.solr.*)
        $config = static::getDomainConfiguration($serviceDomain);

        $base = [];

        if (isset($config->default->adapterClass)) {
            $base['adapterClass'] = $config->default->adapterClass;
        }

        // build resulting service configuration by merging several scopes of
        // configuration to get a flattened set of configuration parameters
        // transparently supporting fallback options (starting with generic
        // parameters to be overwritten by more specific ones)
        $result = new \Zend_Config($base, true);

        // most generic:
        // -> searchengine.solr.default.*
        if (isset($config->default)) {
            $result->merge($config->default);
        }

        // specific to current service, but still common:
        // -> searchengine.solr.<service-name>.*
        if ($serviceName && $serviceName != 'default') {
            if (isset($config->{$serviceName})) {
                $result->merge($config->{$serviceName});
            }
        }

        // common to every type of service in defaults of every service
        // -> searchengine.solr.default.service.default.*
        if (isset($config->default->service->default)) {
            $result->merge($config->default->service->default);
        }

        // specific to selected type of service in defaults of every service
        // -> searchengine.solr.default.service.(search|index|extract).*
        if (isset($config->default->service->{$serviceType})) {
            $result->merge($config->default->service->{$serviceType});
        }

        // merge with most specific options of any service explicitly requested
        // by name
        if ($serviceName && $serviceName != 'default') {
            // common to every type of service in scope of service requested by name
            // -> searchengine.solr.<service-name>.service.default.*
            if (isset($config->{$serviceName}->service->default)) {
                $result->merge($config->{$serviceName}->service->default);
            }

            // specific to selected type of service in scope of service requested by name
            // -> searchengine.solr.<service-name>.service.(search|index|extract).*
            if (isset($config->{$serviceName}->service->{$serviceType})) {
                $result->merge($config->{$serviceName}->service->{$serviceType});
            }
        }


        // finally adopt all basically deprecated service-related configuration
        // (old-style options are thus always preferred over any new-style ones)
        $result = static::mergeWithDeprecatedServiceConfiguration($result, $serviceType);


        $result->setReadOnly();

        self::$configurationsPool[$hash] = $result;

        return $result;
    }

    /**
     * Transparently adopts configuration used in previous releases of Opus4
     * working with Apache's SolrPhpClient.
     *
     * @param \Zend_Config $unqualified
     * @return \Zend_Config
     */
    protected static function mergeWithDeprecatedDomainConfiguration(\Zend_Config $unqualified)
    {
        $config = \Opus_Config::get();

        if ($unqualified->readOnly()) {
            // create writable copy of provided unqualified configuration
            $qualified = new \Zend_Config([], true);
            $qualified->merge($unqualified);
        } else {
            // adjust provided instance directly
            $qualified = $unqualified;
        }


        // merge it with non-service-related setup, only

        // NOTE: searchengine.solr.facets is handled in Opus_Search_Config::getFacetFields()
        // NOTE: searchengine.solr.facetlimit is still used in Opus_Search_Config::getFacetLimits()
        // NOTE: searchengine.solr.globalfacetlimit is still used in Opus_Search_Config::getFacetLimits()
        // NOTE: searchengine.solr.sortcrit is still used in Opus_Search_Config::getFacetSorting()

        // searchengine.solr.xsltfile has been moved to service-related
        // configuration to support different XSLT transformations per
        // service
        if (isset($config->searchengine->solr->xsltfile)) {
            $qualified->merge(new \Zend_Config(
                ['default' => ['service' => ['default' => ['xsltfile' => $config->searchengine->solr->xsltfile]]]]
            ));
        }

        // searchengine.solr.numberOfDefaultSearchResults has been moved to
        // searchengine.solr.parameterDefault.rows to introduce more
        // intuitive support for configuring defaults of query parameters
        if (isset($config->searchengine->solr->numberOfDefaultSearchResults)) {
            $qualified->merge(new \Zend_Config(
                ['parameterDefaults' => ['rows' => $config->searchengine->solr->numberOfDefaultSearchResults]]
            ));
        }


        return $qualified;
    }


    /**
     * Transparently adopts configuration used in previous releases of Opus4
     * working with Apache's SolrPhpClient.
     *
     * @param \Zend_Config $unqualified
     * @return \Zend_Config
     */
    protected static function mergeWithDeprecatedServiceConfiguration(\Zend_Config $unqualified, $serviceType)
    {
        $deprecatedType = null;

        switch ($serviceType) {
            case 'search':
                $deprecatedType = 'index';
                break;
            case 'index':
            case 'extract':
                $deprecatedType = $serviceType;
                break;
            default:
                // service type wasn't supported before -> don't merge anything
                return $unqualified;
        }


        $config = \Opus_Config::get();

        if ($unqualified->readOnly()) {
            // create writable copy of provided unqualified configuration
            $qualified = new \Zend_Config([], true);
            $qualified->merge($unqualified);
        } else {
            // adjust provided instance directly
            $qualified = $unqualified;
        }


        // searchengine.{index,extract}.host
        // searchengine.{index,extract}.port
        // searchengine.{index,extract}.app
        if (isset($config->searchengine->{$deprecatedType}->host)) {
            // ensure to drop multiple new-style endpoint configurations
            $options = [ 'primary' => [
                'host' => $config->searchengine->{$deprecatedType}->host,
                'port' => $config->searchengine->{$deprecatedType}->port,
                'path' => '/' . ltrim($config->searchengine->{$deprecatedType}->app, '/')
            ] ];

            if (isset($config->searchengine->{$deprecatedType}->timeout)) {
                $options['primary']['timeout'] = $config->searchengine->{$deprecatedType}->timeout;
            }

            $qualified->endpoint = new \Zend_Config($options);
        }


        return $qualified;
    }

    /**
     * Retrieves set of field names to use in faceted search.
     *
     * @note Provided name enables use of different sets. Processing
     *       configuration is backward compatible with previous sort of unnamed
     *       configurations.
     *
     * @param string $facetSetName name of configured facets set
     * @param string $serviceDomain name of domain to read configuration of
     * @return string[] probably empty set of found field names to use in faceted search
     * @throws \Zend_Config_Exception
     */
    public static function getFacetFields($facetSetName = null, $serviceDomain = null)
    {
        $facetSetName = is_null($facetSetName) ? 'default' : trim($facetSetName);
        if (! $facetSetName) {
            throw new \InvalidArgumentException('invalid facet set name');
        }

        $config = static::getDomainConfiguration($serviceDomain)->get('facets');

        if ($config instanceof \Zend_Config) {
            // BEST: use configuration in searchengine.solr.facets.$facetSetName
            $sub = $facetSetName ? $config->get($facetSetName) : null;
            if (! ( $sub instanceof \Zend_Config )) {
                // BETTER: use fallback configuration in searchengine.solr.facets.default
                $sub = $config->get('default');
            }

            if ($sub instanceof \Zend_Config) {
                $config = $sub;
            }
            // ELSE: GOOD: use downward-compatible searchengine.solr.facets
        }

        if ($config && is_scalar($config)) {
            $set = preg_split('/[\s,]+/', trim($config), null, PREG_SPLIT_NO_EMPTY);
        } else {
            $set = [];
        }

        if (! in_array('server_state', $set)) {
            $set[] = 'server_state';
        }
        if (! in_array('doctype', $set)) {
            $set[] = 'doctype';
        }
        if (! in_array('year', $set) && ! in_array('year_inverted', $set)) {
            $set[] = 'year';
        }

        $enrichmentFacets = self::getEnrichmentFacets();

        $set = array_merge($set, $enrichmentFacets);

        return $set;
    }

    /**
     * Delivers map of configured facet fields into related limit of matches to
     * obey on faceted search.
     *
     * @param string [$facetSetName] name of particular facet set
     * @param string [$serviceDomain] name of searchengine domain, omit for default ("solr")
     * @return array array mapping field names into count limits (integers)
     */
    public static function getFacetLimits($facetSetName = null, $serviceDomain = null)
    {
        $facetSetName = is_null($facetSetName) ? 'default' : trim($facetSetName);
        if (! $facetSetName) {
            throw new \InvalidArgumentException('invalid facet set name');
        }

        // TODO consolidate configuraton
        $config = static::getDomainConfiguration($serviceDomain);
        $searchConfig = \Opus_Config::get()->search; // new search configuration
        if ($searchConfig && isset($searchConfig->facet->default)) {
            $defaultOptions = $searchConfig->facet->default;
        } else {
            $defaultOptions = new \Zend_Config([]);
        }

        // get configured limits from configuration
        $fieldLimits = $config->get('facetlimit', (object) []);
        $globalLimit = (int) $config->get('globalfacetlimit', 10);
        $globalLimit = (int) $defaultOptions->get('limit', $globalLimit);

        $set = [
            '__global__' => $globalLimit
        ];

        $fields = static::getFacetFields($facetSetName, $serviceDomain);

        foreach ($fields as $field) {
            if (isset($fieldLimits->$field)) {
                $set[$field] = (int) $fieldLimits->$field;
            } else {
                $set[$field] = $globalLimit;
            }
        }

        // TODO hack to support new configuration
        if ($searchConfig) {
            $facetConfig = $searchConfig->get('facet');
            if ($facetConfig) {
                foreach ($facetConfig as $name => $options) {
                    $limit = $options->get('limit');
                    if (! is_null($limit)) {
                        $set[$name] = $limit;
                    }
                }
            }
        }

        // if facet-name is 'year_inverted', the facet values have to be sorted vice versa
        // however, the facet-name should be 'year' (reset in framework ResponseRenderer::getFacets())
        if (array_key_exists('year_inverted', $set)) {
            $set['year'] = $set['year_inverted'];
            $set['year_inverted']; // leave set for query to solr 'year_inverted' facet
        }


        return $set;
    }

    /**
     * Retrieves subset of configured facet fields mapping field's name into
     * "index" _if searchengine.solr.sortcrit is setting either field to "lexi"_.
     *
     * The result always lists fields configured in searchengine.solr.facets,
     * only, but requires either field to be given in searchengine.solr.sortcrit
     * additionally assigning special value there.
     *
     * @param string $facetSetName requests to fetch one of more probably configured facet field sets
     *        (e.g. to have different sets per request purpose)
     * @param string $serviceDomain name of service domain, omit for default ("solr")
     * @return array map of field names into string "index"
     * @throws \Zend_Config_Exception
     */
    public static function getFacetSorting($facetSetName = null, $serviceDomain = null)
    {
        $facetSetName = is_null($facetSetName) ? 'default' : trim($facetSetName);
        if (! $facetSetName) {
            throw new \InvalidArgumentException('invalid facet set name');
        }


        $fields = static::getFacetFields($facetSetName, $serviceDomain);
        $config = static::getDomainConfiguration($serviceDomain)->get('sortcrit', null);
        $searchConfig = \Opus_Config::get()->search; // TODO new configuration (consolidate with old above)

        if ($searchConfig && isset($searchConfig->facet->default->sort)
                && $searchConfig->facet->default->sort == 'lexi') {
            $defaultSort = 'lexi';
        } else {
            $defaultSort = null;
        }


        if ($config instanceof \Zend_Config) {
            // BEST: try configuration in searchengine.solr.sortcrit.$facetSetName
            $sub = $config->get($facetSetName);
            if (! ( $sub instanceof \Zend_Config )) {
                // BETTER: use fallback configuration in searchengine.solr.sortcrit.default
                $sub = $config->get('default');
            }

            if ($sub instanceof \Zend_Config) {
                $config = $sub;
            }
            // ELSE: GOOD: use downward-compatible configuration in searchengine.solr.sortcrit
        }

        if ($config && ! ( $config instanceof \Zend_Config )) {
            throw new \Zend_Config_Exception('invalid facet sorting configuration');
        }

        $set = [];

        if (count($fields) && $config) {
            foreach ($fields as $field) {
                if ($config->get($field) == 'lexi') {
                    $set[$field] = 'index';
                } else if ($config->get($field) !== 'count' && $defaultSort == 'lexi') {
                    $set[$field] = 'index';
                }
            }
        }

        // TODO hack to support new configuration
        if ($searchConfig) {
            $facetConfig = $searchConfig->get('facet');
            if ($facetConfig) {
                foreach ($facetConfig as $name => $options) {
                    $sortCrit = $options->get('sort');
                    if ($sortCrit == 'lexi') {
                        $set[$name] = 'index';
                    } else if ($sortCrit == 'count') {
                        unset($set[$name]);
                    }
                }
            }
        }

        return $set;
    }

    public static function getEnrichmentFacets()
    {
        $names = \Opus_EnrichmentKey::getKeys();

        $config = \Opus_Config::get();

        if (isset($config->search->facet)) {
            $facetConfiguration = $config->search->facet;

            $names = array_filter($names, function ($value) use ($facetConfiguration) {
                if (isset($facetConfiguration->$value)) {
                    $include = filter_var($facetConfiguration->$value->get('active'), FILTER_VALIDATE_BOOLEAN);
                } else {
                    $include = false;
                }

                return $include;
            });

            $facets = array_map(function ($value) {
                $name = str_replace('.', '_', $value);
                return "enrichment_$name";
            }, $names);
        } else {
            $facets = [];
        }

        return $facets;
    }

    public static function getEnrichmentConfig($name)
    {
    }
}
