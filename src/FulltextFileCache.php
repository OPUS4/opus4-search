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

namespace Opus\Search;

use Exception as PhpException;
use Opus\Common\Config as OpusConfig;
use Opus\File;

use function file_get_contents;
use function file_put_contents;
use function filesize;
use function is_readable;
use function is_string;
use function realpath;
use function rename;
use function tempnam;
use function trim;
use function unlink;

/**
 * Cache for fulltext extractions of files.
 *
 * Rather than extracting the texts from PDF and other files over and over again by sending them to Solr the cache
 * allows reusing the extraction results if a document has to be indexed again.
 *
 * The name of the cache file include hashes of the original file. That way a change in the stored file will cause a
 * cache miss and trigger a new extraction request. This makes sure the indexed content matches the actually stored
 * file.
 *
 * TODO report cache misses (detect corruption of files)
 */
class FulltextFileCache
{
    const MAX_FILE_SIZE = 16777216; // 16 MiByte

    /**
     * @return string|null
     * @throws PhpException
     */
    public static function getCacheFileName(File $file)
    {
        $name = null;

        try {
            $hash = $file->getRealHash('md5') . '-' . $file->getRealHash('sha256');
            $name = OpusConfig::get()->workspacePath . "/cache/solr_cache---$hash.txt";
        } catch (PhpException $e) {
            Log::get()->err(
                self::class . '::' . __METHOD__ . ' : could not compute hash values for ' . $file->getPath() . " : $e"
            );
        }

        return $name;
    }

    /**
     * Tries reading cached fulltext data linked with given Opus file from cache.
     *
     * @return false|string found fulltext data, false on missing data in cache
     */
    public static function readOnFile(File $file)
    {
        $fileName = static::getCacheFileName($file);
        if ($fileName && is_readable($fileName)) {
            // TODO: Why keeping huge files in cache if not actually using them but trying to fetch extraction
            //       from remote Solr service over and over again?
            if (filesize($fileName) > self::MAX_FILE_SIZE) {
                Log::get()->info('Skipped reading fulltext HUGE cache file ' . $fileName);
            } else {
                // try reading cached content
                $fileContent = file_get_contents($fileName);
                if ($fileContent !== false) {
                    return trim($fileContent);
                }

                Log::get()->info('Failed reading fulltext cache file ' . $fileName);
            }
        } else {
            Log::get()->debug("Fulltext cache miss for (File ID = {$file->getId()}):  . {$file->getPath()}");
        }

        return false;
    }

    /**
     * Tries writing fulltext data to local cache linked with given Opus file.
     *
     * @note Writing file might fail without notice. Succeeding tests for cached
     *       record are going to fail then, too.
     * @param string $fulltext
     */
    public static function writeOnFile(File $file, $fulltext)
    {
        if (is_string($fulltext)) {
            // try deriving cache file's name first
            $cacheFile = static::getCacheFileName($file);
            if ($cacheFile) {
                // use intermediate temporary file with random name for writing
                // to prevent race conditions on writing cache file
                $tmpPath = realpath(OpusConfig::get()->workspacePath . '/tmp/');
                $tmpFile = tempnam($tmpPath, 'solr_tmp---');

                if (! file_put_contents($tmpFile, trim($fulltext))) {
                    Log::get()->info('Failed writing fulltext temp file ' . $tmpFile);
                } else {
                    // writing temporary file succeeded
                    // -> rename to final cache file (single-step-operation)
                    if (! rename($tmpFile, $cacheFile)) {
                        // failed renaming
                        Log::get()->info('Failed renaming temp file to fulltext cache file ' . $cacheFile);

                        // don't keep temporary file
                        unlink($tmpFile);
                    }
                }
            }
        }
    }
}
