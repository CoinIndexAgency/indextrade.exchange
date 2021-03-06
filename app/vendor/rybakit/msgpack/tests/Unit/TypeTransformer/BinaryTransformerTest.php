<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit\TypeTransformer;

use MessagePack\Type\Binary;
use MessagePack\TypeTransformer\BinaryTransformer;

class BinaryTransformerTest extends \PHPUnit_Framework_TestCase
{
    public function testPackBinary()
    {
        $raw = 'abc';
        $packed = "\xc4\x03\x61\x62\x63";

        $packer = $this->getMockBuilder('MessagePack\Packer')->getMock();
        $packer->expects(self::any())->method('packBin')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new BinaryTransformer();
        $binary = new Binary($raw);

        self::assertSame($packed, $transformer->pack($packer, $binary));
    }

    public function testPackNonBinary()
    {
        $raw = 'abc';
        $packed = "\xc4\x03\x61\x62\x63";

        $packer = $this->getMockBuilder('MessagePack\Packer')->getMock();
        $packer->expects(self::any())->method('packBin')
            ->with($raw)
            ->willReturn($packed);

        $transformer = new BinaryTransformer();

        self::assertNull($transformer->pack($packer, $raw));
    }
}
