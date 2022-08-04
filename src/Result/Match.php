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

namespace Opus\Search\Result;

use Opus\Common\Date;
use Opus\Common\Document;
use Opus\Common\DocumentInterface;
use Opus\Common\Model\NotFoundException;
use RuntimeException;

use function array_key_exists;
use function call_user_func_array;
use function ctype_digit;
use function floatval;
use function intval;
use function is_array;
use function trim;

/**
 * Describes local document as a match in context of a related search query.
 */

class Match
{
    /** @var mixed */
    protected $id;

    /** @var DocumentInterface */
    protected $doc;

    /** @var float */
    protected $score;

    /** @var Date */
    protected $serverDateModified;

    /** @var array */
    protected $fulltextIdSuccess;

    /** @var array */
    protected $fulltextIdFailure;

    /**
     * Caches current document's mapping of containing serieses into document's
     * number in either series.
     *
     * @var string[]
     */
    protected $seriesNumbers;

    /**
     * Collects all additional information related to current match.
     *
     * @var array
     */
    protected $data = [];

    /**
     * @param int $matchId
     */
    public function __construct($matchId)
    {
        $this->id = $matchId;
    }

    /**
     * @param int $matchId
     * @return static
     */
    public static function create($matchId)
    {
        return new static($matchId);
    }

    /**
     * Retrieves ID of document matching related search query.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Retrieves instance of Opus_Document related to current match.
     *
     * @throws NotFoundException
     * @return DocumentInterface
     */
    public function getDocument()
    {
        if ($this->doc === null) {
            $this->doc = Document::get($this->id);
        }

        return $this->doc;
    }

    /**
     * Assigns score of match in context of related search.
     *
     * @param string $score
     * @return $this
     */
    public function setScore($score)
    {
        if ($this->score !== null) {
            throw new RuntimeException('score has been set before');
        }

        $this->score = floatval($score);

        return $this;
    }

    /**
     * Retrieves score of match in context of related search.
     *
     * @return float|null null if score was not set
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * Retrieves matching document's number in series selected by its ID.
     *
     * This method is provided for downward compatibility. You are advised to
     * inspect document's model for this locally available information rather
     * than relying on search engine returning it.
     *
     * @deprecated
     *
     * @param int $seriesId
     * @return string|null
     */
    public function getSeriesNumber($seriesId)
    {
        if (! $seriesId) {
            return null;
        }

        if (! is_array($this->seriesNumbers)) {
            $this->seriesNumbers = [];

            foreach ($this->getDocument()->getSeries() as $linkedSeries) {
                $id     = $linkedSeries->getModel()->getId();
                $number = $linkedSeries->getNumber();

                $this->seriesNumbers[$id] = $number;
            }
        }

        return array_key_exists($seriesId, $this->seriesNumbers) ? $this->seriesNumbers[$seriesId] : null;
    }

    /**
     * Assigns timestamp of last modification to document as tracked in search
     * index.
     *
     * @note This information is temporarily overloading related timestamp in
     *       local document.
     * @param string|int $timestamp Unix timestamp of last modification tracked in search index
     * @return $this fluent interface
     */
    public function setServerDateModified($timestamp)
    {
        if ($this->serverDateModified !== null) {
            throw new RuntimeException('timestamp of modification has been set before');
        }

        $this->serverDateModified = new Date();

        if (ctype_digit($timestamp = trim($timestamp))) {
            $this->serverDateModified->setTimestamp(intval($timestamp));
        } else {
            $this->serverDateModified->setFromString($timestamp);
        }

        return $this;
    }

    /**
     * Provides timestamp of last modification preferring value provided by
     * search engine over value stored locally in document.
     *
     * @note This method is used by Opus to detect outdated records in search
     *       index.
     * @return Date
     */
    public function getServerDateModified()
    {
        if ($this->serverDateModified !== null) {
            return $this->serverDateModified;
        }

        return $this->getDocument()->getServerDateModified();
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setFulltextIDsSuccess($value)
    {
        if ($this->fulltextIdSuccess !== null) {
            throw new RuntimeException('successful fulltext IDs have been set before');
        }

        $this->fulltextIdSuccess = $value;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getFulltextIDsSuccess()
    {
        if ($this->fulltextIdSuccess !== null) {
            return $this->fulltextIdSuccess;
        }

        return null;
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setFulltextIDsFailure($value)
    {
        if ($this->fulltextIdFailure !== null) {
            throw new RuntimeException('failed fulltext IDs have been set before');
        }

        $this->fulltextIdFailure = $value;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getFulltextIDsFailure()
    {
        if ($this->fulltextIdFailure !== null) {
            return $this->fulltextIdFailure;
        }

        return null;
    }

    /**
     * Passes all unknown method invocations to related instance of
     * Opus_Document.
     *
     * @param string  $method name of locally missing/protected method
     * @param mixed[] $args arguments used on invoking that method
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->getDocument(), $method], $args);
    }

    /**
     * Passes access on locally missing/protected property to related instance
     * of Opus_Document.
     *
     * @param string $name name of locally missing/protected property
     * @return mixed value of property
     */
    public function __get($name)
    {
        return $this->getDocument()->{$name};
    }

    /**
     * Attaches named asset to current match.
     *
     * Assets are additional information on match provided by search engine.
     *
     * @param string $name
     * @param mixed  $value
     * @return $this fluent interface
     */
    public function setAsset($name, $value)
    {
        $this->data[$name] = $value;

        return $this;
    }

    /**
     * Retrieves selected asset attached to current match or null if asset was
     * not assigned to match.
     *
     * @param string $name
     * @return mixed|null
     */
    public function getAsset($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Tests if selected asset has been attached to current match.
     *
     * @param string $name name of asset to test
     * @return bool true if asset was assigned to current match
     */
    public function hasAsset($name)
    {
        return array_key_exists($name, $this->data);
    }
}
