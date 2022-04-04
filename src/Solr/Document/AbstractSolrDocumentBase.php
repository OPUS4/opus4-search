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

namespace Opus\Search\Solr\Document;

use DOMDocument;
use Exception as PhpException;
use Opus\Common\Config;
use Opus\Document;
use Opus\File;
use Opus\Model\Xml;
use Opus\Model\Xml\Cache;
use Opus\Model\Xml\Version1;
use Opus\Search\Exception;
use Opus\Search\Log;
use Opus\Search\MimeTypeNotSupportedException;
use Opus\Search\Service;
use Opus\Storage\StorageException;
use Zend_Config;

use function array_filter;
use function count;
use function filter_var;
use function iconv;
use function trim;

use const FILTER_VALIDATE_BOOLEAN;

abstract class AbstractSolrDocumentBase
{
    public function __construct(Zend_Config $options)
    {
    }

    /**
     * Retrieves XML describing model data of provided Opus document.
     *
     * @return DOMDocument
     */
    protected function getModelXml(Document $opusDoc)
    {
        // Set up caching xml-model and get XML representation of document.
        $cachingXmlModel = new Xml();
        $cachingXmlModel->setModel($opusDoc);
        $cachingXmlModel->excludeEmptyFields();
        $cachingXmlModel->setStrategy(new Version1());
        $cache = new Cache(false);
        $cachingXmlModel->setXmlCache($cache);

        $modelXml = $cachingXmlModel->getDomDocument();

        $config = Config::get();

        // extract fulltext from file and append it to the generated xml.
        if (
            ! isset($config->search->indexFiles)
            || filter_var($config->search->indexFiles, FILTER_VALIDATE_BOOLEAN)
        ) {
            $this->attachFulltextToXml($modelXml, $opusDoc->getFile(), $opusDoc->getId());
        }

        return $modelXml;
    }

    /**
     * Appends fulltext data of every listen file to provided XML document.
     *
     * @param DOMDocument $modelXml
     * @param File[]      $files
     * @param string      $docId ID of document
     */
    private function attachFulltextToXml($modelXml, $files, $docId)
    {
        // get root element of XML document containing document's information
        $docXml = $modelXml->getElementsByTagName('Opus_Document')->item(0);
        if ($docXml === null) {
            Log::get()->warn(
                'An error occurred while attaching fulltext information to the xml for document with id '
                . $docId
            );
            return;
        }

        // only consider files which are visible in frontdoor
        /** @var File $file */
        $files = array_filter($files, function ($file) {
            return $file->getVisibleInFrontdoor() === '1';
        });

        if (! count($files)) {
            // any attached file is hidden from public
            $docXml->appendChild($modelXml->createElement('Has_Fulltext', 'false'));
            return;
        }

        $docXml->appendChild($modelXml->createElement('Has_Fulltext', 'true'));

    // fetch reference on probably separate service for extracting fulltext data
        $extractingService = Service::selectExtractingService();

    // extract fulltext data for every file left in set after filtering before
        foreach ($files as $file) {
            $fulltext = '';

            try {
                $fulltext = $extractingService->extractDocumentFile($file);
                $fulltext = trim(iconv("UTF-8", "UTF-8//IGNORE", $fulltext));
            } catch (MimeTypeNotSupportedException $e) {
                Log::get()->err(
                    'An error occurred while getting fulltext data for document with id ' . $docId . ': '
                    . $e->getMessage()
                );
            } catch (StorageException $e) {
                Log::get()->err(
                    'Failed accessing file for extracting fulltext for document with id ' . $docId . ': '
                    . $e->getMessage()
                );
            } catch (Exception $e) {
                Log::get()->err(
                    'An error occurred while getting fulltext data for document with id ' . $docId . ': '
                    . $e->getMessage()
                );
            }

            if ($fulltext !== '') {
                $element = $modelXml->createElement('Fulltext_Index');
                $element->appendChild($modelXml->createCDATASection($fulltext));
                $docXml->appendChild($element);

                $element = $modelXml->createElement('Fulltext_ID_Success');
                $element->appendChild($modelXml->createTextNode($this->getFulltextHash($file)));
                $docXml->appendChild($element);
            } else {
                $element = $modelXml->createElement('Fulltext_ID_Failure');
                $element->appendChild($modelXml->createTextNode($this->getFulltextHash($file)));
                $docXml->appendChild($element);
            }
        }
    }

    /**
     * @return string
     */
    private function getFulltextHash(File $file)
    {
        $hash = '';

        try {
            $hash = $file->getRealHash('md5');
        } catch (PhpException $e) {
            Log::get()->err('could not compute MD5 hash for ' . $file->getPath() . ' : ' . $e);
        }

        return $file->getId() . ':' . $hash;
    }

    /*
     *
     * --- abstract part of API ---
     *
     */

    /**
     * Derives Solr-compatible description of document from provided Opus
     * document.
     *
     * @note Parameter $solrDoc must pass reference on object providing proper
     *       API for describing Solr-compatible document. On return the same
     *       reference is returned.
     * @param mixed $solrDoc depends on derived implementation
     * @return mixed reference provided in parameter $solrDoc
     */
    abstract public function toSolrDocument(Document $opusDoc, $solrDoc);
}
