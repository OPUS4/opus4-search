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
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search\Util;

use Exception as PhpException;
use Opus\Search\Config;
use Opus\Search\Facet\Set;
use Opus\Search\InvalidQueryException;
use Opus\Search\InvalidServiceException;
use Opus\Search\Log;
use Opus\Search\Result\Base;
use Opus\Search\SearchException;
use Opus\Search\Service;
use Opus\Search\Solr\Filter\Raw;
use Zend_Acl;
use Zend_Exception;

class Searcher
{
    /** @var array Holds numbers of facets */
    private $facetArray;

    /** @var Zend_Acl Access control list */
    private $acl;

    public function __construct()
    {
    }

    /**
     * @param Query $query
     * @param bool  $validateDocIds check document IDs coming from Solr index against database
     * @return Base
     * @throws SearchException If Solr server responds with an error or the response is empty.
     */
    public function search($query, $validateDocIds = true)
    {
        try {
            Log::get()->debug("query: " . $query->getQ());

            // get service adapter for searching
            $service = Service::selectSearchingService(null, 'solr');

            // basically create query
            $request = $service->createQuery()
                ->setFilter(new Raw($query->getQ()))
                ->setStart($query->getStart())
                ->setRows($query->getRows());

            switch ($query->getSearchType()) {
                case Query::LATEST_DOCS:
                    $request
                        ->addSorting($query->getSortField(), $query->getSortOrder());
                    // TODO fall through on purpose here?

                case Query::DOC_ID:
                    if ($query->isReturnIdsOnly()) {
                        $request
                            ->setFields('id');
                    } else {
                        $request
                            ->setFields(['*', 'score']);
                    }
                    break;

                case Query::FACET_ONLY:
                    $facet = Set::create()
                        ->setFacetOnly();

                    $facet->addField($query->getFacetField())
                        ->setMinCount(1)
                        ->setLimit(-1);

                    $request->setFacet($facet);
                    break;

                default:
                    $request->addSorting($query->getSortField(), $query->getSortOrder());

                    if ($query->isReturnIdsOnly()) {
                        $request
                            ->setFields('id');
                    } else {
                        $request
                            ->setFields(['*', 'score']);

                        $facet = Set::create();

                        if (isset($this->facetArray)) {
                            $facet->overrideLimits($this->facetArray);
                        }

                        $fields = Config::getFacetFields($facet->getSetName(), 'solr');
                        if (empty($fields)) {
                            // no facets are being configured
                            Log::get()->warn("Key searchengine.solr.facets is not present in config. No facets will be displayed.");
                        } else {
                            $request->setFacet($facet->setFields($fields));
                        }
                    }
            }

            // TODO make this dependend on user
            if (! $this->isAdmin()) {
                $query->addFilterQuery('server_state', 'published');
            }

            $fq = $query->getFilterQueries();

            if (! empty($fq)) {
                foreach ($fq as $index => $sub) {
                    $request->setSubFilter("fq$index", new Raw($sub));
                }
            }

            $response = $service->customSearch($request);

            if ($validateDocIds) {
                $response->dropLocallyMissingMatches();
            }

            return $response;
        } catch (InvalidServiceException $e) {
            $this->mapException(SearchException::SERVER_UNREACHABLE, $e);
        } catch (InvalidQueryException $e) {
            $this->mapException(SearchException::INVALID_QUERY, $e);
        } catch (SearchException $e) {
            $this->mapException(null, $e);
        }
    }

    /**
     * @param mixed $type
     * @throws SearchException
     */
    private function mapException($type, PhpException $previousException)
    {
        $msg = 'Solr server responds with an error ' . $previousException->getMessage();
        Log::get()->err($msg);

        throw new SearchException($msg, $type, $previousException);
    }

    /**
     * @param array $array
     */
    public function setFacetArray($array)
    {
        $this->facetArray = $array;
    }

    /**
     * Sets access control list object for controlling access to facets.
     *
     * @param Zend_Acl $acl
     */
    public function setAcl($acl)
    {
        $this->acl = $acl;
    }

    /**
     * Checks if user is document administrator.
     *
     * This allows access to documents that have not been published yet.
     *
     * @return bool
     * @throws Zend_Exception
     */
    public function isAdmin()
    {
        if ($this->acl !== null) {
            // TODO dependency to Application_Security_AclProvider::ACTIVE_ROLE = '_user';
            // TODO knowledge from application ('documents') - delegate implementation to application
            return $this->acl->isAllowed('_user', 'documents');
        } else {
            return false;
        }
    }
}
