<?php
declare(strict_types=1);
namespace RCS\Util;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(\RCS\Util\UrlDataObject::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class UrlDataObjectTest extends TestCase // NOSONAR - too many methods
{
    private const TEST_KEY_A = 'testKeyA';
    private const TEST_KEY_B = 'testKeyB';
    private const TEST_KEY_C = 'testKeyC';

    private const TEST_STRING = 'test data';
    private const TEST_INTEGER = 3254;

    private const NON_BASE64_STR = 'ABC!@#DEF8978';

    /**
     * Runs before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\setUp();
    }

    /**
     * Runs after each test.
     */
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();

        parent::tearDown();
    }

    private function buildEncodedString(): ?string
    {
        $setupObj = new UrlDataObject();
        $setupProp = new \stdClass();

        $setupProp->{self::TEST_KEY_A} = self::TEST_STRING;
        $setupProp->{self::TEST_KEY_B} = strval(self::TEST_INTEGER);

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $setupProp, $setupObj);

        return $setupObj->encode();
    }

    public function testSet_returnObject(): void
    {
        $testObj = new UrlDataObject();

        $retObj = $testObj->set(self::TEST_KEY_A, self::TEST_STRING);

        self::assertSame($testObj, $retObj);
    }

    public function testSet_singleProp(): void
    {
        $testObj = new UrlDataObject();

        $testObj->set(self::TEST_KEY_A, self::TEST_STRING);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_A, $dataProp);

        self::assertEquals(self::TEST_STRING, $dataProp->{self::TEST_KEY_A});
    }

    public function testSet_multiProp(): void
    {
        $testObj = new UrlDataObject();

        $testObj->set(self::TEST_KEY_A, strval(self::TEST_INTEGER));
        $testObj->set(self::TEST_KEY_B, self::TEST_STRING);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_A, $dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_B, $dataProp);

        self::assertEquals(self::TEST_INTEGER, $dataProp->{self::TEST_KEY_A});
        self::assertEquals(self::TEST_STRING, $dataProp->{self::TEST_KEY_B});
    }

    public function testGet_singleProp(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        $testValue = $testObj->get(self::TEST_KEY_A);

        self::assertNotNull($testValue);
        self::assertEquals(self::TEST_STRING, $testValue);
    }

    public function testGet_multiProp(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;
        $testProp->{self::TEST_KEY_B} = strval(self::TEST_INTEGER);

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        $testValue = $testObj->get(self::TEST_KEY_B);

        self::assertNotNull($testValue);
        self::assertEquals(self::TEST_INTEGER, $testValue);

        $testValue = $testObj->get(self::TEST_KEY_A);

        self::assertNotNull($testValue);
        self::assertEquals(self::TEST_STRING, $testValue);
    }

    public function testGet_missingKey(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        $testValue = $testObj->get(self::TEST_KEY_C);

        self::assertNull($testValue);
    }

    #[Depends('testSet_multiProp')]
    #[Depends('testGet_multiProp')]
    public function testSetGet_multiProp(): void
    {
        $testObj = new UrlDataObject();

        $testObj->set(self::TEST_KEY_A, self::TEST_STRING);
        $testObj->set(self::TEST_KEY_B, strval(self::TEST_INTEGER));

        self::assertEquals(self::TEST_INTEGER, $testObj->get(self::TEST_KEY_B));
        self::assertEquals(self::TEST_STRING, $testObj->get(self::TEST_KEY_A));
    }

    public function testEncode_emptyData(): void
    {
        $testObj = new UrlDataObject();
        $testStr = $testObj->encode();

        self::assertNull($testStr);
    }

    public function testEncode_singleProp(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        $testStr = $testObj->encode();

        self::assertNotNull($testStr);
        self::assertStringNotContainsString(self::TEST_KEY_A, $testStr);
        self::assertStringNotContainsString(self::TEST_STRING, $testStr);
    }

    public function testEncode_multiProp(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;
        $testProp->{self::TEST_KEY_B} = strval(self::TEST_INTEGER);

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        $testStr = $testObj->encode();

        self::assertNotNull($testStr);
        self::assertStringNotContainsString(self::TEST_KEY_A, $testStr);
        self::assertStringNotContainsString(self::TEST_KEY_B, $testStr);
        self::assertStringNotContainsString(strval(self::TEST_INTEGER), $testStr);
        self::assertStringNotContainsString(self::TEST_STRING, $testStr);
    }

    #[Test]
    public function testEncode_jsonEncodeError(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        \Brain\Monkey\Functions\when('json_encode')->alias(fn () => false);

        $result = $testObj->encode();

        self::assertNull($result);
    }

    public function testEncode_deflateError(): void
    {
        $testObj = new UrlDataObject();
        $testProp = new \stdClass();

        $testProp->{self::TEST_KEY_A} = self::TEST_STRING;

        ReflectionHelper::setObjectProperty(UrlDataObject::class, 'data', $testProp, $testObj);

        \Brain\Monkey\Functions\when('gzdeflate')->alias(fn () => false);

        $result = $testObj->encode();

        self::assertNull($result);
    }

    public function testDecode_emptyString(): void
    {
        $testObj = new UrlDataObject();

        $result = $testObj->decode('');

        self::assertFalse($result);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    public function testDecode_noneBase64String(): void
    {
        $testObj = new UrlDataObject();

        $result = $testObj->decode(self::NON_BASE64_STR);

        self::assertFalse($result);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    #[Depends('testEncode_multiProp')]
    public function testDecode_jsonDecodeFailure(): void
    {
        $encStr = $this->buildEncodedString();

        \Brain\Monkey\Functions\when('json_decode')->alias(fn () => false);

        $testObj = new UrlDataObject();
        $result = $testObj->decode($encStr);

        self::assertFalse($result);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    #[Depends('testEncode_multiProp')]
    public function testDecode_base64DecodeFailure(): void
    {
        $encStr = $this->buildEncodedString();

        \Brain\Monkey\Functions\when('base64_decode')->alias(fn () => false);

        $testObj = new UrlDataObject();
        $result = $testObj->decode($encStr);

        self::assertFalse($result);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    #[Depends('testEncode_multiProp')]
    public function testDecode_gzinflateFailure(): void
    {
        $encStr = $this->buildEncodedString();

        \Brain\Monkey\Functions\when('gzinflate')->alias(fn () => false);

        $testObj = new UrlDataObject();
        $result = $testObj->decode($encStr);

        self::assertFalse($result);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    #[Depends('testEncode_multiProp')]
    public function testDecode_success(): void
    {
        $testStr = $this->buildEncodedString();

        $testObj = new UrlDataObject();
        $result = $testObj->decode($testStr);

        self::assertTrue($result);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_A, $dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_B, $dataProp);

        self::assertEquals(self::TEST_INTEGER, $dataProp->{self::TEST_KEY_B});
        self::assertEquals(self::TEST_STRING, $dataProp->{self::TEST_KEY_A});
    }

    public function testCtor_emptyString(): void
    {
        $testObj = new UrlDataObject('');

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    public function testCtor_noneBase64String(): void
    {
        $testObj = new UrlDataObject(self::NON_BASE64_STR);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertEmpty((array)$dataProp);
    }

    public function testCtor_success(): void
    {
        $encStr = $this->buildEncodedString();

        $testObj = new UrlDataObject($encStr);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_A, $dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_B, $dataProp);

        self::assertEquals(self::TEST_INTEGER, $dataProp->{self::TEST_KEY_B});
        self::assertEquals(self::TEST_STRING, $dataProp->{self::TEST_KEY_A});
    }

    public function testSet_chainedCalls(): void
    {
        $testObj = (new UrlDataObject())
            ->set(self::TEST_KEY_A, strval(self::TEST_INTEGER))
            ->set(self::TEST_KEY_B, self::TEST_STRING);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_A, $dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_B, $dataProp);

        self::assertEquals(self::TEST_INTEGER, $dataProp->{self::TEST_KEY_A});
        self::assertEquals(self::TEST_STRING, $dataProp->{self::TEST_KEY_B});
    }

    public function testAdd_returnObject(): void
    {
        $testObj = new UrlDataObject();
        $retObj = $testObj->add([
            self::TEST_KEY_A => self::TEST_STRING,
            self::TEST_KEY_C => self::TEST_INTEGER
        ]);

        self::assertSame($testObj, $retObj);
    }

    public function testAdd_validPairs(): void
    {
        $testObj = new UrlDataObject();
        $testObj->add([
            self::TEST_KEY_A => self::TEST_STRING,
            self::TEST_KEY_C => self::TEST_INTEGER
            ]);

        $dataProp = ReflectionHelper::getObjectProperty(UrlDataObject::class, 'data', $testObj);

        self::assertNotNull($dataProp);
        self::assertIsObject($dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_A, $dataProp);
        self::assertObjectHasProperty(self::TEST_KEY_C, $dataProp);

        self::assertEquals(self::TEST_STRING, $dataProp->{self::TEST_KEY_A});
        self::assertEquals(self::TEST_INTEGER, $dataProp->{self::TEST_KEY_C});
    }

    public function testUrlStringAlreadyDecoded(): void
    {
        $data = ['rcdId' => 'Blair', 'frm' => 'cprn_consent_form', 'pdf' => 'Consent Form', 'evt' => 'initial_visit_arm_1'];

        $inputObj = new UrlDataObject();
        $inputObj->add($data);

        $urlStr = $inputObj->encode();

        $urlStr = urldecode($urlStr);   // Simulate server decoding the URL string

        $testObj = new UrlDataObject();
        $result = $testObj->decode($urlStr, false);

        self::assertTrue($result, 'Failed to decode string');
        foreach($data as $key => $testValue) {
            $value = $testObj->get($key);
            self::assertEquals($testValue, $value);
        }
    }
}
