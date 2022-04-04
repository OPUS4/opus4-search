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
 * @copyright   Copyright (c) 2009-2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Search;

use Opus\Document;

/**
 * Defines methods provided for indexing data in (Solr-based) search database.
 */

interface IndexingInterface
{
    /**
     * Adds provided set of Opus_Document instances to index.
     *
     * @note Implementing methods MUST ensure to keep index in consistent state
     *       by adding all listed documents as part of a transaction to be
     *       rolled back on any error.
     * @param Document|Document[] $documents set of documents to add
     * @return $this
     * @throws Exception In case of error.
     */
    public function addDocumentsToIndex($documents);

    /**
     * Removes provided set of Opus_Document instances from index.
     *
     * @note Implementing methods MUST ensure to keep index in consistent state
     *       by adding all listed documents as part of a transaction to be
     *       rolled back on any error.
     * @param Document|Document[] $documents set of document to remove
     * @return $this
     * @throws Exception In case of error.
     */
    public function removeDocumentsFromIndex($documents);

    /**
     * Removes provided set of Opus_Document instances from index.
     *
     * @note Implementing methods MUST ensure to keep index in consistent state
     *       by adding all listed documents as part of a transaction to be
     *       rolled back on any error.
     * @param int|int[] $documentIds set of IDs of documents to remove
     * @return $this
     * @throws Exception In case of error.
     */
    public function removeDocumentsFromIndexById($documentIds);

    /**
     * Removes all documents from index.
     *
     * @note Implementing methods MUST ensure to keep index in consistent state
     *       by adding all listed documents as part of a transaction to be
     *       rolled back on any error.
     * @return $this
     * @throws Exception In case of error.
     */
    public function removeAllDocumentsFromIndex();
}
