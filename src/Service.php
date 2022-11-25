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
use ReflectionClass;
use Zend_Config;
use Zend_Config_Exception;

use function array_key_exists;
use function is_string;
use function trim;

class Service
{
    /** @var array */
    protected static $adaptersPool = [];

    /**
     * Selects service type for querying search index.
     */

    const SERVICE_TYPE_SEARCH = "search";

    /**
     * Selects service type for indexing/updating search index.
     */

    const SERVICE_TYPE_INDEX = "index";

    /**
     * Selects service type for extracting full text from documents utilizing
     * search index.
     */

    const SERVICE_TYPE_EXTRACT = "extract";

    /**
     * Drops any cached service adapter.
     */
    public static function dropCached()
    {
        self::$adaptersPool = [];
    }

    /**
     * Validates provided explicit selection of search domain using any
     * configured domain by default.
     *
     * @note If configuration is missing explicit definition of default search
     *       domain, "solr" is returned by default.
     * @param null|string $searchDomain explicitly selected search domain
     * @return string
     */
    public static function getQualifiedDomain($searchDomain = null)
    {
        if ($searchDomain === null) {
            $config       = Config::getConfiguration();
            $searchDomain = $config->get('domain', 'solr');
        }

        if (! is_string($searchDomain) || ! trim($searchDomain)) {
            throw new InvalidArgumentException('invalid default search domain');
        }

        return trim($searchDomain);
    }

    /**
     * @param string      $serviceType one out of 'index', 'search' or 'extract'
     * @param string      $serviceInterface required interface of service adapter, e.g. 'Opus_Search_Indexing'
     * @param string|null $serviceName name of configured service to work with
     * @param null|string $serviceDomain name of domain selected service belongs to
     * @return IndexingInterface|SearchingInterface|ExtractingInterface
     * @throws Zend_Config_Exception
     */
    protected static function selectService(
        $serviceType,
        $serviceInterface,
        $serviceName = null,
        $serviceDomain = null
    ) {
        // manage pool of domains
        $serviceDomain = static::getQualifiedDomain($serviceDomain);

        if (! array_key_exists($serviceDomain, self::$adaptersPool)) {
            self::$adaptersPool[$serviceDomain] = [
                'index'   => [],
                'search'  => [],
                'extract' => [],
            ];
        }

        $domainPool = &self::$adaptersPool[$serviceDomain];

        // select one of several probably configured service
        if (! $serviceName) {
            $serviceName = 'default';
        }

        if (! array_key_exists($serviceName, $domainPool[$serviceType])) {
            $config = Config::getServiceConfiguration($serviceType, $serviceName, $serviceDomain);

            $className = $config->adapterClass;
            if (! $className || $className instanceof Zend_Config) {
                throw new Zend_Config_Exception('missing search engine adapter');
            }

            $class = new ReflectionClass($className);

            if (! $class->implementsInterface($serviceInterface)) {
                throw new Zend_Config_Exception('invalid search engine adapter');
            }

            $domainPool[$serviceType][$serviceName] = $class->newInstance($serviceName, $config);
        }

        return $domainPool[$serviceType][$serviceName];
    }

    /**
     * @param string|null $serviceName name of configured service to work with
     * @param null|string $serviceDomain name of domain selected service belongs to
     * @return IndexingInterface
     * @throws Zend_Config_Exception
     */
    public static function selectIndexingService($serviceName = null, $serviceDomain = null)
    {
        return static::selectService('index', IndexingInterface::class, $serviceName, $serviceDomain);
    }

    /**
     * @param string|null $serviceName name of configured service to work with
     * @param null|string $serviceDomain name of domain selected service belongs to
     * @return SearchingInterface
     * @throws Zend_Config_Exception
     */
    public static function selectSearchingService($serviceName = null, $serviceDomain = null)
    {
        return static::selectService('search', SearchingInterface::class, $serviceName, $serviceDomain);
    }

    /**
     * @param string|null $serviceName name of configured service to work with
     * @param null|string $serviceDomain name of domain selected service belongs to
     * @return ExtractingInterface
     * @throws Zend_Config_Exception
     */
    public static function selectExtractingService($serviceName = null, $serviceDomain = null)
    {
        return static::selectService('extract', ExtractingInterface::class, $serviceName, $serviceDomain);
    }
}
