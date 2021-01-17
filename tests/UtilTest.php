<?php declare(strict_types=1);

namespace Tolkam\DOM\Manipulator\Tests;

use PHPUnit\Framework\TestCase;
use Tolkam\DOM\Manipulator\Util;

class UtilTest extends TestCase
{
    public function testCssStringToArray()
    {
        $this->assertEquals([
            'font-size' => '15px',
            'font-weight' => 'bold',
            'font-color' => 'black',
        ], Util::cssStringToArray('invalid_css_string;font-size: 15px;font-weight: bold;font-color: black;'));
    }
    
    public function testCssArrayToString()
    {
        $this->assertEquals('font-size: 15px;font-weight: bold;font-color: black;', Util::cssArrayToString([
            'font-size' => '15px',
            'font-weight' => 'bold',
            'font-color' => 'black',
        ]));
    }
}
