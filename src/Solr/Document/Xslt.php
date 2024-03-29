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

namespace Opus\Search\Solr\Document;

use DOMDocument;
use Exception;
use InvalidArgumentException;
use Opus\Common\Config;
use Opus\Common\DocumentInterface;
use Opus\Search\Log;
use XSLTProcessor;
use Zend_Config;

use function array_key_exists;
use function array_map;
use function ctype_digit;
use function dirname;
use function filter_var;
use function in_array;
use function is_int;
use function preg_split;
use function strlen;
use function strtolower;
use function strval;
use function trim;

use const DIRECTORY_SEPARATOR;
use const FILTER_VALIDATE_BOOLEAN;
use const PREG_SPLIT_NO_EMPTY;

class Xslt extends AbstractSolrDocumentBase
{
    /** @var XSLTProcessor */
    protected $processor;

    /** @var Zend_Config */
    private $options;

    /** @var string[] Names of enrichment fields to be excluded from indexing */
    private static $enrichmentBlacklist;

    public function __construct(Zend_Config $options)
    {
        parent::__construct($options);

        $this->options = $options;

        try {
            $xslt = new DOMDocument();

            $xslt->load($this->getXsltFile());

            $this->processor = new XSLTProcessor();
            $this->processor->importStyleSheet($xslt);
            $this->processor->registerPHPFunctions([
                'Opus\Search\Solr\Document\Xslt::indexYear',
                'Opus\Search\Solr\Document\Xslt::indexEnrichment',
            ]);
        } catch (Exception $e) {
            throw new InvalidArgumentException('invalid XSLT file for deriving Solr documents', 0, $e);
        }
    }

    /**
     * Returns names of enrichment fields to be excluded from indexing.
     *
     * @return string[]
     */
    public static function getEnrichmentBlacklist()
    {
        if (self::$enrichmentBlacklist === null) {
            $blacklist = [];
            $config    = Config::get();

            if (isset($config->search->index->enrichment->blacklist)) {
                $configBlacklist = $config->search->index->enrichment->blacklist;
                $blacklist       = array_map('strtolower', preg_split('/[\s,]+/', trim($configBlacklist), 0, PREG_SPLIT_NO_EMPTY));
            }

            self::$enrichmentBlacklist = $blacklist;
        }

        return self::$enrichmentBlacklist;
    }

    /**
     * Derives Solr-compatible description in XML format of provided Opus
     * document.
     *
     * @note Parameter $solrDoc must be prepared with reference on instance of
     *       DOMDocument. It is returned on return.
     * @example
     *     $solrXmlDoc = $doc->toSolrDocument( $opusDoc, new DOMDocument() );
     * @param DOMDocument $solrDoc
     * @return DOMDocument
     */
    public function toSolrDocument(DocumentInterface $opusDoc, $solrDoc)
    {
        if (! $solrDoc instanceof DOMDocument) {
            throw new InvalidArgumentException('provided Solr document must be instance of DOMDocument');
        }

        $modelXml = $this->getModelXml($opusDoc);

        $solrDoc->preserveWhiteSpace = false;
        $solrDoc->loadXML($this->processor->transformToXML($modelXml));

        if (filter_var(Config::get()->log->prepare->xml, FILTER_VALIDATE_BOOLEAN)) {
            $modelXml->formatOutput = true;
            Log::get()->debug("input xml\n" . $modelXml->saveXML());
            $solrDoc->formatOutput = true;
            Log::get()->debug("transformed solr xml\n" . $solrDoc->saveXML());
        }

        return $solrDoc;
    }

    /**
     * @return string
     */
    public function getXsltFile()
    {
        $path = $this->options->xsltfile;

        if ($path === null || strlen(trim($path)) === 0) {
            $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'solr.xslt';
        }

        return $path;
    }

    /**
     * TODO move somewhere else
     * TODO do not use static functions (see ApplicationXslt)
     * TODO handle configuration more efficiently
     *
     * @param string $publishedDateYear
     * @param string $publishedYear
     * @param string $completedDateYear
     * @param string $completedYear
     * @return string
     */
    public static function indexYear($publishedDateYear, $publishedYear, $completedDateYear, $completedYear)
    {
        $fields                  = [];
        $fields['PublishedDate'] = $publishedDateYear;
        $fields['PublishedYear'] = $publishedYear;
        $fields['CompletedDate'] = $completedDateYear;
        $fields['CompletedYear'] = $completedYear;

        $year = '';

        $order = self::getYearOrder();

        foreach ($order as $fieldName) {
            if (array_key_exists($fieldName, $fields)) {
                $year = $fields[$fieldName];
                if (is_int($year)) {
                    $year = strval($year);
                }
                if ($year !== null && ctype_digit($year)) {
                    // use the first value found
                    break;
                }
            }
        }

        return $year;
    }

    /** @var string */
    private static $yearOrder;

    /**
     * @return array|false|string[]
     */
    public static function getYearOrder()
    {
        if (self::$yearOrder === null) {
            $config = Config::get();

            if (isset($config->search->index->field->year->order)) {
                $orderConfig = $config->search->index->field->year->order;
            } else {
                $orderConfig = 'PublishedDate,PublishedYear'; // old default
            }

            $order = preg_split('/[\s,]+/', trim($orderConfig), 0, PREG_SPLIT_NO_EMPTY);

            self::$yearOrder = $order;
        }

        return self::$yearOrder;
    }

    /**
     * @param string $order
     * TODO hack necessary for testing - refactor all of this
     */
    public static function setYearOrder($order)
    {
        self::$yearOrder = $order;
    }

    /**
     * Returns true if the enrichment field with the given name should
     * be included in the Solr index, otherwise returns false.
     *
     * Note that comparison of field names is performed case-insensitive.
     *
     * @param string $fieldName Name of enrichment field
     * @return bool
     */
    public static function indexEnrichment($fieldName)
    {
        $blacklist = self::getEnrichmentBlacklist();

        return ! in_array(strtolower($fieldName), $blacklist, true);
    }
}
