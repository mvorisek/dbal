<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class DateTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var MockPlatform
     */
    private $_platform;

    /**
     * @var \Doctrine\DBAL\Types\DateType
     */
    private $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('date');
    }

    public function testDateConvertsToDatabaseValue()
    {
        $this->assertInternalType('string', $this->_type->convertToDatabaseValue(new \DateTime(), $this->_platform));
    }

    /**
     * @dataProvider invalidPHPValuesProvider
     *
     * @param mixed $value
     */
    public function testInvalidTypeConversionToDatabaseValue($value)
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');

        $this->_type->convertToDatabaseValue($value, $this->_platform);
    }

    public function testDateConvertsToPHPValue()
    {
        // Birthday of jwage and also birthday of Doctrine. Send him a present ;)
        $this->assertTrue(
            $this->_type->convertToPHPValue('1985-09-01', $this->_platform)
            instanceof \DateTime
        );
    }

    public function testDateResetsNonDatePartsToZeroUnixTimeValues()
    {
        $date = $this->_type->convertToPHPValue('1985-09-01', $this->_platform);

        $this->assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testDateRests_SummerTimeAffection()
    {
        date_default_timezone_set('Europe/Berlin');

        $date = $this->_type->convertToPHPValue('2009-08-01', $this->_platform);
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
        $this->assertEquals('2009-08-01', $date->format('Y-m-d'));

        $date = $this->_type->convertToPHPValue('2009-11-01', $this->_platform);
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
        $this->assertEquals('2009-11-01', $date->format('Y-m-d'));
    }

    public function testInvalidDateFormatConversion()
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->_type->convertToPHPValue('abcdefg', $this->_platform);
    }

    public function testNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testConvertDateTimeToPHPValue()
    {
        $date = new \DateTime("now");
        $this->assertSame($date, $this->_type->convertToPHPValue($date, $this->_platform));
    }

    /**
     * @return mixed[][]
     */
    public function invalidPHPValuesProvider()
    {
        return [
            [0],
            [''],
            ['foo'],
            ['10:11:12'],
            ['2015-01-31'],
            ['2015-01-31 10:11:12'],
            [new \stdClass()],
            [$this],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
        ];
    }
}
