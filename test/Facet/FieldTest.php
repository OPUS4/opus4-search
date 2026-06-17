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
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Search\Facet;

use InvalidArgumentException;
use Opus\Search\Facet\Field;
use OpusTest\Search\TestAsset\SimpleTestCase;

class FieldTest extends SimpleTestCase
{
    public function testConstruct()
    {
        $field = new Field('author_facet');

        $this->assertEquals('author_facet', $field->getName());
    }

    public function testCreate()
    {
        $field = Field::create('author_facet');

        $this->assertEquals('author_facet', $field->getName());
    }

    public function testConstructInvalidFieldname()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid facet field name');
        $field = new Field(100);
    }

    public function testConstructInvalidFieldnameEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid facet field name');
        $field = new Field(' ');
    }

    public function testSetLimit()
    {
        $field = Field::create('author_facet');

        $field->setLimit('10000');

        $this->assertEquals(10000, $field->getLimit());
    }

    public function testSetLimitInvalid()
    {
        $field = Field::create('author_facet');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid limit value');
        $field->setLimit('all');
    }

    public function testSetMinCount()
    {
        $field = Field::create('author_facet');

        $field->setMinCount(2);

        $this->assertEquals(2, $field->getMinCount());

        $field->setMinCount(1);

        $this->assertEquals(1, $field->getMinCount());
    }

    public function testSetMinCountInvalidArgument()
    {
        $field = Field::create('author_facet');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid minCount value');
        $field->setMinCount('all');
    }
}
