<?php
declare(strict_types=1);
namespace RCS\WP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlDataObject::class)]
final class UrlDataObjectTest extends TestCase
{
    public function testAddAndGet(): void
    {
        $obj = new UrlDataObject();
        $obj->add(['foo' => 'bar', 'baz' => 'qux']);

        $this->assertSame('bar', $obj->get('foo'));
        $this->assertSame('qux', $obj->get('baz'));
        $this->assertNull($obj->get('missing'));
    }

    public function testSetOverridesAdd(): void
    {
        $obj = new UrlDataObject();
        $obj->add(['key' => 'value1']);
        $obj->set('key', 'value2');

        $this->assertSame('value2', $obj->get('key'));
    }

    public function testEncodeAndDecode(): void
    {
        $obj = new UrlDataObject();
        $obj->add(['a' => '1', 'b' => '2']);
        $encoded = $obj->encode();

        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);

        $decodedObj = new UrlDataObject();
        $this->assertTrue($decodedObj->decode($encoded));
        $this->assertSame('1', $decodedObj->get('a'));
        $this->assertSame('2', $decodedObj->get('b'));
    }

    public function testDecodeWithUrlDecodedString(): void
    {
        $obj = new UrlDataObject();
        $obj->set('x', 'y');

        $encoded = $obj->encode();
        $this->assertIsString($encoded);

        $decodedObj = new UrlDataObject();
        // Pass the string without urldecode
        $this->assertFalse($decodedObj->decode($encoded, false));
    }

    public function testDecodeInvalidStringReturnsFalse(): void
    {
        $obj = new UrlDataObject();

        $this->assertFalse($obj->decode('invalid-string'));
        $this->assertNull($obj->get('any'));
    }

    public function testConstructorDecodesEncodedString(): void
    {
        $obj1 = new UrlDataObject();
        $obj1->set('k', 'v');
        $encoded = $obj1->encode();

        $obj2 = new UrlDataObject($encoded);
        $this->assertSame('v', $obj2->get('k'));
    }

    public function testEmptyEncodeReturnsNull(): void
    {
        $obj = new UrlDataObject();
        $this->assertNull($obj->encode());
    }
}
