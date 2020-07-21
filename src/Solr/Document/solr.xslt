<?xml version="1.0" encoding="utf-8"?>
<!--
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
 * @category    Framework
 * @package     Opus_SolrSearch
 * @author      Oliver Marahrens <o.marahrens@tu-harburg.de>
 * @author      Sascha Szott <szott@zib.de>
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2008-2019, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */
-->

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:php="http://php.net/xsl"
    exclude-result-prefixes="php">

    <xsl:output method="xml" indent="yes" />

    <!-- Suppress output for all elements that don't have an explicit template. -->
    <xsl:template match="*" />

    <xsl:template match="/">
        <xsl:element name="add">
            <xsl:element name="doc">

                <!-- id -->
                <xsl:element name="field">
                    <xsl:attribute name="name">id</xsl:attribute>
                    <xsl:value-of select="/Opus/Opus_Document/@Id" />
                </xsl:element>

                <!-- year -->
                <xsl:variable name="year">
                    <xsl:value-of select="php:functionString('Opus\Search\Solr\Document\Xslt::indexYear',
                        /Opus/Opus_Document/PublishedDate/@Year,
                        /Opus/Opus_Document/@PublishedYear,
                        /Opus/Opus_Document/CompletedDate/@Year,
                        /Opus/Opus_Document/@CompletedYear)" />
                </xsl:variable>

                <xsl:if test="$year != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">year</xsl:attribute>
                        <xsl:value-of select="$year"/>
                    </xsl:element>
                </xsl:if>

                <!-- year_inverted -->
                <xsl:if test="$year != ''">
                    <xsl:variable name="yearInverted" select="65535 - $year"/>
                    <xsl:element name="field">
                        <xsl:attribute name="name">year_inverted</xsl:attribute>
                        <xsl:value-of select="$yearInverted"/>:<xsl:value-of select="$year"/>
                    </xsl:element>
                </xsl:if>

                <!-- published_year -->
                <xsl:variable name="publishedYear">
                    <xsl:choose>
                        <xsl:when test="/Opus/Opus_Document/PublishedDate/@Year != ''">
                            <xsl:value-of select="/Opus/Opus_Document/PublishedDate/@Year" />
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="/Opus/Opus_Document/@PublishedYear" />
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:variable>

                <xsl:if test="$publishedYear != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">published_year</xsl:attribute>
                        <xsl:value-of select="$publishedYear"/>
                    </xsl:element>
                </xsl:if>

                <!-- published_year_inverted -->
                <xsl:if test="$publishedYear != ''">
                    <xsl:variable name="publishedYearInverted" select="65535 - $publishedYear"/>
                    <xsl:element name="field">
                        <xsl:attribute name="name">published_year_inverted</xsl:attribute>
                        <xsl:value-of select="$publishedYearInverted"/>:<xsl:value-of select="$publishedYear"/>
                    </xsl:element>
                </xsl:if>

                <!-- completed_year -->
                <xsl:variable name="completedYear">
                    <xsl:choose>
                        <xsl:when test="/Opus/Opus_Document/CompletedDate/@Year != ''">
                            <xsl:value-of select="/Opus/Opus_Document/CompletedDate/@Year" />
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="/Opus/Opus_Document/@CompletedYear" />
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:variable>

                <xsl:if test="$completedYear != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">completed_year</xsl:attribute>
                        <xsl:value-of select="$completedYear"/>
                    </xsl:element>
                </xsl:if>

                <!-- completed_year_inverted -->
                <xsl:if test="$completedYear != ''">
                    <xsl:variable name="completedYearInverted" select="65535 - $completedYear"/>
                    <xsl:element name="field">
                        <xsl:attribute name="name">completed_year_inverted</xsl:attribute>
                        <xsl:value-of select="$completedYearInverted"/>:<xsl:value-of select="$completedYear"/>
                    </xsl:element>
                </xsl:if>

                <!-- server_date_published -->
                <xsl:if test="/Opus/Opus_Document/ServerDatePublished/@UnixTimestamp != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">server_date_published</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/ServerDatePublished/@UnixTimestamp" />
                    </xsl:element>
                </xsl:if>

                <!-- server_date_modified -->
                <xsl:if test="/Opus/Opus_Document/ServerDateModified/@UnixTimestamp != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">server_date_modified</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/ServerDateModified/@UnixTimestamp" />
                    </xsl:element>
                </xsl:if>

                <!-- language -->
                <xsl:variable name="language" select="/Opus/Opus_Document/@Language" />
                <xsl:if test="$language != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">language</xsl:attribute>
                        <xsl:value-of select="$language" />
                    </xsl:element>
                </xsl:if>

                <!-- title / title_output -->
                <xsl:for-each select="/Opus/Opus_Document/TitleMain">
                    <xsl:element name="field">
                        <xsl:attribute name="name">title</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                    <xsl:if test="@Language = $language">
                        <xsl:element name="field">
                            <xsl:attribute name="name">title_output</xsl:attribute>
                            <xsl:value-of select="@Value" />
                        </xsl:element>
                    </xsl:if>
                </xsl:for-each>

                <!-- abstract / abstract_output -->
                <xsl:for-each select="/Opus/Opus_Document/TitleAbstract">
                    <xsl:element name="field">
                        <xsl:attribute name="name">abstract</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                    <xsl:if test="@Language = $language">
                        <xsl:element name="field">
                            <xsl:attribute name="name">abstract_output</xsl:attribute>
                            <xsl:value-of select="@Value" />
                        </xsl:element>
                    </xsl:if>
                </xsl:for-each>

                <!-- author -->
                <xsl:for-each select="/Opus/Opus_Document/PersonAuthor">
                    <xsl:element name="field">
                        <xsl:attribute name="name">author</xsl:attribute>
                        <xsl:value-of select="@LastName" />
                        <xsl:if test="@FirstName">
                            <xsl:text>, </xsl:text>
                            <xsl:value-of select="@FirstName" />
                        </xsl:if>
                    </xsl:element>
                </xsl:for-each>

                <!-- author_sort -->
                <xsl:variable name="authorSort">
                    <xsl:for-each select="/Opus/Opus_Document/PersonAuthor">
                        <xsl:value-of select="@LastName" />
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="@FirstName" />
                        <xsl:text> </xsl:text>
                    </xsl:for-each>
                </xsl:variable>

                <xsl:if test="$authorSort != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">author_sort</xsl:attribute>
                        <xsl:value-of select="$authorSort" />
                    </xsl:element>
                </xsl:if>

                <!-- fulltext -->
                <xsl:for-each select="/Opus/Opus_Document/Fulltext_Index">
                    <xsl:element name="field">
                        <xsl:attribute name="name">fulltext</xsl:attribute>
                        <xsl:value-of select="." />
                    </xsl:element>
                </xsl:for-each>

                <!-- has fulltext -->
                <xsl:element name="field">
                    <xsl:attribute name="name">has_fulltext</xsl:attribute>
                    <xsl:value-of select="/Opus/Opus_Document/Has_Fulltext" />
                </xsl:element>

                <!-- IDs der Dateien, die mit nicht leerem Resultat extrahiert werden konnten -->
                <xsl:for-each select="/Opus/Opus_Document/Fulltext_ID_Success">
                    <xsl:element name="field">
                        <xsl:attribute name="name">fulltext_id_success</xsl:attribute>
                        <xsl:value-of select="."/>
                    </xsl:element>
                </xsl:for-each>

                <!-- IDs der Dateien, die nicht erfolgreich extrahiert werden konnten -->
                <xsl:for-each select="/Opus/Opus_Document/Fulltext_ID_Failure">
                    <xsl:element name="field">
                        <xsl:attribute name="name">fulltext_id_failure</xsl:attribute>
                        <xsl:value-of select="."/>
                    </xsl:element>
                </xsl:for-each>

                <!-- referee -->
                <xsl:for-each select="/Opus/Opus_Document/PersonReferee">
                    <xsl:element name="field">
                        <xsl:attribute name="name">referee</xsl:attribute>
                        <xsl:value-of select="@FirstName" />
                        <xsl:text> </xsl:text>
                        <xsl:value-of select="@LastName" />
                    </xsl:element>
                </xsl:for-each>

                <!-- other persons (non-authors) -->
                <xsl:for-each select="/Opus/Opus_Document/*">
                    <xsl:if test="local-name() != 'Person' and local-name() != 'PersonAuthor' and local-name() != 'PersonSubmitter' and substring(local-name(), 1, 6) = 'Person'">
                        <xsl:element name="field">
                            <xsl:attribute name="name">persons</xsl:attribute>
                            <xsl:value-of select="@FirstName" />
                            <xsl:text> </xsl:text>
                            <xsl:value-of select="@LastName" />
                        </xsl:element>
                    </xsl:if>
                </xsl:for-each>

                <!-- doctype -->
                <xsl:variable name="doctype" select="/Opus/Opus_Document/@Type" />
                <xsl:if test="$doctype != ''">
                    <xsl:element name="field">
                        <xsl:attribute name="name">doctype</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/@Type" />
                    </xsl:element>
                </xsl:if>

                <!-- state -->
                <xsl:element name="field">
                    <xsl:attribute name="name">server_state</xsl:attribute>
                    <xsl:value-of select="/Opus/Opus_Document/@ServerState" />
                </xsl:element>

                <!-- subject (swd) -->
                <xsl:for-each select="/Opus/Opus_Document/Subject[@Type = 'swd']">
                    <xsl:element name="field">
                        <xsl:attribute name="name">subject</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                </xsl:for-each>

                <!-- subject (uncontrolled) -->
                <xsl:for-each select="/Opus/Opus_Document/Subject[@Type = 'uncontrolled']">
                    <xsl:element name="field">
                        <xsl:attribute name="name">subject</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                </xsl:for-each>

                <!-- belongs_to_bibliography -->
                <xsl:element name="field">
                    <xsl:attribute name="name">belongs_to_bibliography</xsl:attribute>
                    <xsl:choose>
                        <xsl:when test="/Opus/Opus_Document/@BelongsToBibliography = 0" >
                            <xsl:text>false</xsl:text>
                       </xsl:when>
                       <xsl:otherwise>
                            <xsl:text>true</xsl:text>
                       </xsl:otherwise>
                    </xsl:choose>
                </xsl:element>

                <!-- collections: project, app_area, institute, ids -->
                <xsl:for-each select="/Opus/Opus_Document/Collection">
                    <xsl:choose>
                        <xsl:when test="@RoleName = 'projects'">
                            <xsl:element name="field">
                                <xsl:attribute name="name">project</xsl:attribute>
                                <xsl:value-of select="@Number" />
                            </xsl:element>
                        </xsl:when>
                        <xsl:when test="@RoleName = 'institutes'">
                            <xsl:element name="field">
                                <xsl:attribute name="name">institute</xsl:attribute>
                                <xsl:value-of select="@Name" />
                            </xsl:element>
                        </xsl:when>
                    </xsl:choose>

                    <xsl:element name="field">
                        <xsl:attribute name="name">collection_ids</xsl:attribute>
                        <xsl:value-of select="@Id" />
                    </xsl:element>
                </xsl:for-each>

                <!-- title parent -->
                <xsl:for-each select="/Opus/Opus_Document/TitleParent">
                    <xsl:element name="field">
                        <xsl:attribute name="name">title_parent</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                </xsl:for-each>

                <!-- title sub -->
                <xsl:for-each select="/Opus/Opus_Document/TitleSub">
                    <xsl:element name="field">
                        <xsl:attribute name="name">title_sub</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                </xsl:for-each>

                <!-- title additional -->
                <xsl:for-each select="/Opus/Opus_Document/TitleAdditional">
                    <xsl:element name="field">
                        <xsl:attribute name="name">title_additional</xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                </xsl:for-each>

                <!-- series ids and series number per id (modeled as dynamic field) -->
                <xsl:for-each select="/Opus/Opus_Document/Series">
                    <xsl:element name="field">
                        <xsl:attribute name="name">series_ids</xsl:attribute>
                        <xsl:value-of select="@Id"/>
                    </xsl:element>

                    <xsl:element name="field">
                        <xsl:attribute name="name">
                            <xsl:text>series_number_for_id_</xsl:text><xsl:value-of select="@Id"/>
                        </xsl:attribute>
                        <xsl:value-of select="@Number"/>
                    </xsl:element>

                    <xsl:element name="field">
                        <xsl:attribute name="name">
                            <xsl:text>doc_sort_order_for_seriesid_</xsl:text><xsl:value-of select="@Id"/>
                        </xsl:attribute>
                        <xsl:value-of select="@DocSortOrder"/>
                    </xsl:element>
                </xsl:for-each>

                <!-- creating corporation (single valued) -->
                <xsl:if test="/Opus/Opus_Document/@CreatingCorporation">
                    <xsl:element name="field">
                        <xsl:attribute name="name">creating_corporation</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/@CreatingCorporation"/>
                    </xsl:element>
                </xsl:if>

                <!-- contributing corporation (single valued) -->
                <xsl:if test="/Opus/Opus_Document/@ContributingCorporation">
                    <xsl:element name="field">
                        <xsl:attribute name="name">contributing_corporation</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/@ContributingCorporation"/>
                    </xsl:element>
                </xsl:if>

                <!-- publisher name (single valued) -->
                <xsl:if test="/Opus/Opus_Document/@PublisherName">
                    <xsl:element name="field">
                        <xsl:attribute name="name">publisher_name</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/@PublisherName"/>
                    </xsl:element>
                </xsl:if>

                <!-- publisher place (single valued) -->
                <xsl:if test="/Opus/Opus_Document/@PublisherPlace">
                    <xsl:element name="field">
                        <xsl:attribute name="name">publisher_place</xsl:attribute>
                        <xsl:value-of select="/Opus/Opus_Document/@PublisherPlace"/>
                    </xsl:element>
                </xsl:if>

                <!-- identifier (multi valued) -->
                <xsl:for-each select="/Opus/Opus_Document/Identifier">
                    <xsl:element name="field">
                        <xsl:attribute name="name">identifier</xsl:attribute>
                        <xsl:value-of select="@Value"/>
                    </xsl:element>
                </xsl:for-each>

                <!-- enrichment -->
                <!-- TODO configurable inclusion of enrichments -->
                <xsl:for-each select="/Opus/Opus_Document/Enrichment">
                    <xsl:element name="field">
                        <xsl:attribute name="name">enrichment_<xsl:value-of select="./@KeyName" /></xsl:attribute>
                        <xsl:value-of select="@Value" />
                    </xsl:element>
                </xsl:for-each>

            </xsl:element>
        </xsl:element>
    </xsl:template>
</xsl:stylesheet>
