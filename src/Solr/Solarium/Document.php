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

namespace Opus\Search\Solr\Solarium;

use DOMDocument;
use InvalidArgumentException;
use Opus\Common\DocumentInterface;
use Opus\Search\Solr\Document\Xslt;
use Solarium\QueryType\Update\Query\Document as SolariumDocument;
use Zend_Config;

use function simplexml_import_dom;
use function strval;

/**
 * Implements description of solr documents when using `Solarium` client
 * library.
 *
 * To keep things compatible with previous releases this implementation is
 * transforming Opus_Document instances to generic XML first for transforming
 * that to some Solr-specific XML to be parsed and read back finally. This is
 * basically due to supporting customized XSLT transformations.
 */

class Document extends Xslt
{
    public function __construct(Zend_Config $options)
    {
        parent::__construct($options);
    }

    /**
     * Derives Solr-compatible description in XML format of provided Opus
     * document.
     *
     * @note Parameter $solrDoc must be prepared with reference on Solr document
     *       to be added or updated. It is returned on return.
     * @example
     *     $update  = $solariumClient->createUpdate();
     *     $solrDoc = $update->addDocument();
     *     $solrDoc = $doc->toSolrDocument( $opusDoc, $solrDoc );
     * @param SolariumDocument $solrDoc
     * @return SolariumDocument
     */
    public function toSolrDocument(DocumentInterface $opusDoc, $solrDoc)
    {
        if (! $solrDoc instanceof SolariumDocument) {
            throw new InvalidArgumentException('provided Solr document must be instance of Solarium Update Document');
        }

        // convert Opus document to Solr XML document for supporting custom transformations
        $solrDomDoc = parent::toSolrDocument($opusDoc, new DOMDocument());

        // read back fields from generated Solr XML document
        $solrXmlDoc = simplexml_import_dom($solrDomDoc)->doc[0];

        $solrDoc->clear();
        foreach ($solrXmlDoc->field as $field) {
            $solrDoc->addField(strval($field['name']), strval($field));
        }

        return $solrDoc;
    }
}
