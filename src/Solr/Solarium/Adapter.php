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

namespace Opus\Search\Solr\Solarium;

use DOMDocument;
use Exception as PhpException;
use InvalidArgumentException;
use Opus\Common\Config;
use Opus\Common\DocumentInterface;
use Opus\Common\FileInterface;
use Opus\Common\Storage\FileAccessException;
use Opus\Common\Storage\FileNotFoundException;
use Opus\Common\Storage\StorageException;
use Opus\Search\AbstractAdapter;
use Opus\Search\ExtractingInterface;
use Opus\Search\FulltextFileCache;
use Opus\Search\IndexingInterface;
use Opus\Search\InvalidQueryException;
use Opus\Search\InvalidServiceException;
use Opus\Search\Log;
use Opus\Search\MimeTypeNotSupportedException;
use Opus\Search\Query;
use Opus\Search\Result\Base;
use Opus\Search\SearchException;
use Opus\Search\SearchingInterface;
use Opus\Search\Solr\Filter\Raw;
use Opus\Search\Solr\Solarium\Filter\Complex;
use Solarium\Client as SolariumClient;
use Solarium\Core\Query\Query as SolariumCoreQuery;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Extract\Result as ExtractResult;
use Solarium\QueryType\Select\Query\Query as SolariumQuery;
use Solarium\QueryType\Select\Result\Document as SolariumDocument;
use Solarium\QueryType\Select\Result\Result;
use Zend_Config;
use Zend_Exception;

use function array_chunk;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_shift;
use function file_exists;
use function filesize;
use function filter_var;
use function in_array;
use function intval;
use function is_array;
use function is_readable;
use function is_string;
use function preg_match;
use function preg_split;
use function sprintf;
use function substr;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_INT;
use const PREG_SPLIT_NO_EMPTY;

class Adapter extends AbstractAdapter implements IndexingInterface, SearchingInterface, ExtractingInterface
{
    /** @var Zend_Config */
    protected $options;

    /** @var SolariumClient */
    protected $client;

    /**
     * @param string $serviceName
     * @param array  $options
     * @throws SearchException
     * @throws InvalidQueryException
     * @throws InvalidServiceException
     */
    public function __construct($serviceName, $options)
    {
        $this->options = $options;
        $this->client  = new SolariumClient($options);

        // ensure service is basically available
        $ping = $this->client->createPing();
        $this->execute($ping, 'failed pinging service ' . $serviceName);
    }

    /**
     * @param SolariumCoreQuery $query
     * @param string            $actionText
     * @return ResultInterface
     * @throws SearchException
     * @throws InvalidQueryException
     * @throws InvalidServiceException
     */
    protected function execute($query, $actionText)
    {
        try {
            $result = $this->client->execute($query);
        } catch (HttpException $e) {
            $msg = sprintf('%s: %d %s', $actionText, $e->getCode(), $e->getStatusMessage());

            if ($e->getCode() === 404 || $e->getCode() >= 500) {
                throw new InvalidServiceException($msg, $e->getCode(), $e);
            }

            if ($e->getCode() === 400) {
                throw new InvalidQueryException($msg, $e->getCode(), $e);
            }

            throw new SearchException($msg, $e->getCode(), $e);
        }

        if ($result->getStatus()) {
            throw new SearchException($actionText, $result->getStatus());
        }

        return $result;
    }

    /**
     * Maps name of field returned by search engine into name of asset to use
     * on storing field's value in context of related match.
     *
     * This mapping relies on runtime configuration. Mapping is defined per
     * service in
     *
     * @param string $fieldName
     * @return string
     */
    protected function mapResultFieldToAsset($fieldName)
    {
        $assetName = null;

        if ($this->options->fieldToAsset instanceof Zend_Config) {
            $assetName = $this->options->fieldToAsset->get($fieldName);
            if ($assetName !== null) {
                return $assetName;
            }
        }

        // TODO hack to map published_year_inverted (all year index fields) to year asset (should be cleaned up)
        $config = Config::get();

        if (isset($config->search->facet)) {
            $facets = $config->search->facet;
            foreach ($facets as $facetName => $facetConfig) {
                $indexField = $facetConfig->get('indexField');
                if ($indexField === $fieldName) {
                    $assetName = $facetName;
                    break;
                }
            }
        }

        if ($assetName === null) {
            return $fieldName;
        } else {
            return $assetName;
        }
    }

    /*
     *
     * -- part of Opus_Search_Adapter --
     *
     */

    /**
     * @return string
     */
    public function getDomain()
    {
        return 'solr';
    }

    /*
     *
     * -- part of Opus_Search_Indexing --
     *
     */

    /**
     * @param array $documents
     * @return array
     */
    protected function normalizeDocuments($documents)
    {
        if (! is_array($documents)) {
            $documents = [$documents];
        }

        $validDocuments = [];

        foreach ($documents as $document) {
            if (! $document instanceof DocumentInterface) {
                throw new InvalidArgumentException("invalid document in provided set");
            }
            if ($document->getServerState() !== 'temporary') {
                $validDocuments[] = $document;
            }
        }

        return $validDocuments;
    }

    /**
     * @param array|int $documentIds
     * @return array
     */
    protected function normalizeDocumentIds($documentIds)
    {
        if (! is_array($documentIds)) {
            $documentIds = [$documentIds];
        }

        foreach ($documentIds as $id) {
            if (! $id) {
                throw new InvalidArgumentException("invalid document ID in provided set");
            }
        }

        return $documentIds;
    }

    /**
     * @param DocumentInterface|DocumentInterface[] $documents
     * @return $this
     * @throws SearchException
     * @throws InvalidQueryException
     * @throws InvalidServiceException
     */
    public function addDocumentsToIndex($documents)
    {
        $documents = $this->normalizeDocuments($documents);

        $builder = new Document($this->options);

        $errors = [];

        try {
            // split provided set of documents into chunks of 16 documents
            $slices = array_chunk($documents, $this->options->get('updateChunkSize', 16));

            // update documents of every chunk in a separate request
            foreach ($slices as $slice) {
                $update = $this->client->createUpdate();

                $updateDocs = array_map(function ($opusDoc) use ($builder, $update) {
                    return $builder->toSolrDocument($opusDoc, $update->createDocument());
                }, $slice);

                $update->addDocuments($updateDocs);

                $this->execute($update, 'failed updating slice of documents');
            }

            // finally commit all updates
            $update = $this->client->createUpdate();
            $update->addCommit();

            $this->execute($update, 'failed committing update of documents');

            return $this;
        } catch (SearchException $e) {
            Log::get()->err($e->getMessage());

            if ($this->options->get('rollback', 1)) {
                // roll back updates due to failure
                $update = $this->client->createUpdate();
                $update->addRollback();

                try {
                    $this->execute($update, 'failed rolling back update of documents');
                } catch (SearchException $inner) {
                    // SEVERE case: rolling back failed, too
                    Log::get()->alert($inner->getMessage());
                }
            }

            throw $e;
        }
    }

    /**
     * @param DocumentInterface|DocumentInterface[] $documents
     * @return $this
     * @throws SearchException
     * @throws InvalidQueryException
     * @throws InvalidServiceException
     */
    public function removeDocumentsFromIndex($documents)
    {
        $documents = $this->normalizeDocuments($documents);

        $documentIds = array_map(function ($doc) {
            /** @var DocumentInterface $doc */
            return $doc->getId();
        }, $documents);

        return $this->removeDocumentsFromIndexById($documentIds);
    }

    /**
     * @param int|int[] $documentIds
     * @return $this
     * @throws SearchException
     * @throws InvalidQueryException
     * @throws InvalidServiceException
     */
    public function removeDocumentsFromIndexById($documentIds)
    {
        $documentIds = $this->normalizeDocumentIds($documentIds);

        try {
            // split provided set of documents into chunks of 128 documents
            $slices = array_chunk($documentIds, $this->options->get('deleteChunkSize', 128));

            // delete documents of every chunk in a separate request
            foreach ($slices as $deleteIds) {
                $delete = $this->client->createUpdate();
                $delete->addDeleteByIds($deleteIds);

                $this->execute($delete, 'failed deleting slice of documents');
            }

            // finally commit all deletes
            $update = $this->client->createUpdate();
            $update->addCommit();

            $this->execute($update, 'failed committing deletion of documents');

            return $this;
        } catch (SearchException $e) {
            Log::get()->err($e->getMessage());

            if ($this->options->get('rollback', 1)) {
                // roll back deletes due to failure
                $update = $this->client->createUpdate();
                $update->addRollback();

                try {
                    $this->execute($update, 'failed rolling back update of documents');
                } catch (SearchException $inner) {
                    // SEVERE case: rolling back failed, too
                    Log::get()->alert($inner->getMessage());
                }
            }

            throw $e;
        }
    }

    /**
     * @return $this
     * @throws SearchException
     * @throws InvalidQueryException
     * @throws InvalidServiceException
     */
    public function removeAllDocumentsFromIndex()
    {
        $update = $this->client->createUpdate();

        $update->addDeleteQuery('*:*');
        $update->addCommit();

        $this->execute($update, 'failed removing all documents from index');

        return $this;
    }

    /*
     *
     * -- part of Opus_Search_Searching --
     *
     */

    /**
     * @return Base
     * @throws SearchException
     */
    public function customSearch(Query $query)
    {
        $search = $this->client->createSelect();

        return $this->processQuery($this->applyParametersOnQuery($search, $query, false));
    }

    /**
     * @param string $name
     * @return Base
     * @throws SearchException
     * @throws InvalidQueryException
     */
    public function namedSearch($name, ?Query $customization = null)
    {
        if (! preg_match('/^[a-z_]+$/i', $name)) {
            throw new SearchException('invalid name of pre-defined query: ' . $name);
        }

        // lookup named query in configuration of current service
        if (isset($this->options->query->{$name})) {
            $definition = $this->options->query->{$name};
        } else {
            $definition = null;
        }

        if (! $definition || ! $definition instanceof Zend_Config) {
            throw new InvalidQueryException('selected query is not pre-defined: ' . $name);
        }

        $search = $this->client->createSelect($definition);

        return $this->processQuery($this->applyParametersOnQuery($search, $customization, true));
    }

    /**
     * @return Query
     */
    public function createQuery()
    {
        return new Query();
    }

    /**
     * @return Complex
     */
    public function createFilter()
    {
        return new Complex($this->client);
    }

    /**
     * Executs prepared query fetching all listed instances of Opus_Document on
     * success.
     *
     * @param SolariumQuery $query
     * @return Base
     * @throws SearchException
     */
    protected function processQuery($query)
    {
        // send search query to service
        /** @var Result $request */
        $request = $this->execute($query, 'failed querying search engine');

        // create result descriptor
        $result = Base::create()
            ->setAllMatchesCount($request->getNumFound())
            ->setQueryTime($request->getQueryTime());

        // add description on every returned match
        $excluded = 0;
        foreach ($request->getDocuments() as $document) {
            /** @var SolariumDocument $document */
            $fields = $document->getFields();

            if (array_key_exists('id', $fields)) {
                $match = $result->addMatch($fields['id']);

                foreach ($fields as $fieldName => $fieldValue) {
                    switch ($fieldName) {
                        case 'id':
                            break;

                        case 'score':
                            $match->setScore($fieldValue);
                            break;

                        case 'server_date_modified':
                            $match->setServerDateModified($fieldValue);
                            break;

                        case 'fulltext_id_success':
                            $match->setFulltextIDsSuccess($fieldValue);
                            break;

                        case 'fulltext_id_failure':
                            $match->setFulltextIDsFailure($fieldValue);
                            break;

                        default:
                            $match->setAsset($this->mapResultFieldToAsset($fieldName), $fieldValue);
                            break;
                    }
                }
            } else {
                $excluded++;
            }
        }

        if ($excluded > 0) {
            Log::get()->warn(sprintf(
                'search yielded %d matches not available in result set for missing ID of related document',
                $excluded
            ));
        }

        // add returned results of faceted search
        $facetResult = $request->getFacetSet();
        if ($facetResult) {
            foreach ($facetResult->getFacets() as $fieldName => $facets) {
                foreach ($facets as $value => $occurrences) {
                    $result->addFacet($fieldName, $value, $occurrences);
                }
            }
        }

        return $result;
    }

    /**
     * Adjusts provided query depending on explicitly defined parameters.
     *
     * @param bool $preferOriginalQuery true for keeping existing query in $query
     * @return mixed
     */
    protected function applyParametersOnQuery(
        SolariumQuery $query,
        ?Query $parameters = null,
        $preferOriginalQuery = false
    ) {
        if ($parameters) {
            $subfilters = $parameters->getSubFilters();
            if ($subfilters !== null) {
                foreach ($subfilters as $name => $subfilter) {
                    if ($subfilter instanceof Raw || $subfilter instanceof Complex) {
                        $query->createFilterQuery($name)
                            ->setQuery($subfilter->compile($query));
                    }
                }
            }

            $filter = $parameters->getFilter();
            if ($filter instanceof Raw || $filter instanceof Complex) {
                if (! $query->getQuery() || ! $preferOriginalQuery) {
                    $compiled = $filter->compile($query);
                    if ($compiled !== null) {
                        // compile() hasn't implicitly assigned query before
                        $query->setQuery($compiled);
                    }
                }
            }

            $start = $parameters->getStart();
            if ($start !== null) {
                $query->setStart(intval($start));
            }

            $rows = $parameters->getRows();
            if ($rows !== null) {
                $query->setRows(intval($rows));
            }

            $union = $parameters->getUnion();
            if ($union !== null) {
                $query->setQueryDefaultOperator($union ? 'OR' : 'AND');
            }

            $fields = $parameters->getFields();
            if ($fields !== null) {
                $query->setFields($fields);
            }

            $sortings = $parameters->getSort();
            if ($sortings !== null) {
                $query->setSorts($sortings);
            }

            $facet = $parameters->getFacet();
            if ($facet !== null) {
                $facetSet = $query->getFacetSet();
                foreach ($facet->getFields() as $field) {
                    $facetSet->createFacetField($field->getName())
                        ->setField($field->getName())
                        ->setMinCount($field->getMinCount())
                        ->setLimit($field->getLimit())
                        ->setSort($field->getSort() ? 'index' : null);
                }

                if ($facet->isFacetOnly()) {
                    $query->setFields([]);
                }
            }
        }

        return $query;
    }

    /*
     *
     * -- part of Opus_Search_Extracting --
     *
     */

    /**
     * @return string
     * @throws SearchException
     * @throws StorageException
     * @throws FileAccessException
     * @throws FileNotFoundException
     * @throws MimeTypeNotSupportedException
     * @throws Zend_Exception
     */
    public function extractDocumentFile(FileInterface $file, ?DocumentInterface $document = null)
    {
        Log::get()->debug('extracting fulltext from ' . $file->getPath());

        try {
            // ensure file is basically available and extracting is supported
            if (! $file->exists()) {
                $path = $file->getPath();

                /** TODO shorten path
                 * if (substr($path, 0, strlen(APPLICATION_PATH)) == APPLICATION_PATH) {
                 * $path = substr($path, strlen(APPLICATION_PATH) + 1);
                 * }
                 */
                throw new FileNotFoundException($path);
            }

            if (! $file->isReadable()) {
                throw new FileAccessException($file->getPath() . ' is not readable.');
            }

            if (! $this->isMimeTypeSupported($file)) {
                throw new MimeTypeNotSupportedException(
                    "Extracting MIME type {$file->getMimeType()} not supported ({$file->getPath()})"
                );
            }

            // use cached result of previous extraction if available
            $fulltext = FulltextFileCache::readOnFile($file);
            if ($fulltext !== false) {
                Log::get()->info('Found cached fulltext for file ' . $file->getPath());
                return $fulltext;
            }

            if (filesize($file->getPath())) {
                // query Solr service for extracting fulltext data
                $extract = $this->client->createExtract()
                    ->setExtractOnly(true)
                    ->setFile($file->getPath())
                    ->setCommit(true);

                $result = $this->execute($extract, 'failed extracting fulltext data');

                // got response -> extract
                /** @var ExtractResult $response */
                $response = $result->getData();
                $fulltext = null;

                if (is_array($response)) {
                    $keys = array_keys($response);
                    foreach ($keys as $k => $key) {
                        if (substr($key, -9) === '_metadata' && array_key_exists(substr($key, 0, -9), $response)) {
                            unset($response[$key]);
                        }
                    }

                    $fulltextData = array_shift($response);
                    if (is_string($fulltextData)) {
                        if (substr($fulltextData, 0, 6) === '<?xml ') {
                            $dom = new DOMDocument();
                            $dom->loadHTML($fulltextData);
                            $body = $dom->getElementsByTagName("body")->item(0);
                            if ($body) {
                                $fulltext = $body->textContent;
                            } else {
                                $fulltext = $dom->textContent;
                            }
                        } else {
                            $fulltext = $fulltextData;
                        }
                    }
                }

                if ($fulltext === null) {
                    Log::get()->err('failed extracting fulltext data from solr response');
                    $fulltext = '';
                } else {
                    $fulltext = trim($fulltext);
                }
            } else {
                // empty file -> empty fulltext index
                $fulltext = '';
            }

            // always write returned fulltext data to cache to keep client from
            // re-extracting same file as query has been processed properly this
            // time
            FulltextFileCache::writeOnFile($file, $fulltext);

            return $fulltext;
        } catch (SearchException $e) {
            if (! $e instanceof SearchException && ! $e instanceof StorageException) {
                $e = new SearchException('error while extracting fulltext from file ' . $file->getPath(), null, $e);
            }

            Log::get()->err($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $path
     * @return string
     * @throws SearchException
     * @throws StorageException
     */
    public function extractFile($path)
    {
        Log::get()->debug('extracting fulltext from ' . $path);

        try {
            // ensure file is basically available and extracting is supported
            if (! file_exists($path)) {
                throw new PhpException("$path not found");
            }

            if (! is_readable($path)) {
                throw new PhpException("$path is not readable.");
            }

            if (filesize($path)) {
                // query Solr service for extracting fulltext data
                $extract = $this->client->createExtract()
                    ->setExtractOnly(true)
                    ->setFile($path)
                    ->setCommit(true);

                $result = $this->execute($extract, 'failed extracting fulltext data');
                // @var ExtractResult $response

                // got response -> extract
                $response = $result->getData();
                $fulltext = null;

                if (is_array($response)) {
                    $keys = array_keys($response);
                    foreach ($keys as $k => $key) {
                        if (substr($key, -9) === '_metadata' && array_key_exists(substr($key, 0, -9), $response)) {
                            unset($response[$key]);
                        }
                    }

                    $fulltextData = array_shift($response);
                    if (is_string($fulltextData)) {
                        if (substr($fulltextData, 0, 6) === '<?xml ') {
                            $dom = new DOMDocument();
                            $dom->loadHTML($fulltextData);
                            $body = $dom->getElementsByTagName("body")->item(0);
                            if ($body) {
                                $fulltext = $body->textContent;
                            } else {
                                $fulltext = $dom->textContent;
                            }
                        } else {
                            $fulltext = $fulltextData;
                        }
                    }
                }

                if ($fulltext === null) {
                    Log::get()->err('failed extracting fulltext data from solr response');
                    $fulltext = '';
                } else {
                    $fulltext = trim($fulltext);
                }
            } else {
                // empty file -> empty fulltext index
                $fulltext = '';
            }

            return $fulltext;
        } catch (SearchException $e) {
            if (! $e instanceof SearchException && ! $e instanceof StorageException) {
                $e = new SearchException("error while extracting fulltext from file $path", null, $e);
            }

            Log::get()->err($e->getMessage());
            throw $e;
        }
    }

    /**
     * Detects if provided file has MIME type supported for extracting fulltext
     * data.
     *
     * @return bool
     *
     * TODO make list configurable
     */
    protected function isMimeTypeSupported(FileInterface $file)
    {
        $mimeType = $file->getMimeType();

        $mimeType = preg_split('/[;\s]+/', trim($mimeType), null, PREG_SPLIT_NO_EMPTY)[0];

        if ($mimeType) {
            $supported = $this->options->get("supportedMimeType", [
                'text/html',
                'text/plain',
                'application/pdf',
                'application/postscript',
                'application/xhtml+xml',
                'application/xml',
            ]);

            return in_array($mimeType, (array) $supported);
        }

        return false;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        if ($timeout === null || filter_var($timeout, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) === null) {
            throw new InvalidArgumentException('Argument timeout must be an integer');
        }

        $options = $this->client->getOptions();
        if (isset($options['endpoint'])) {
            $keys                                     = array_keys($options['endpoint']);
            $options['endpoint'][$keys[0]]['timeout'] = $timeout;
            $this->client->setOptions($options, true);
        }
    }
}
