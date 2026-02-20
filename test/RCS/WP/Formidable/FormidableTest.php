<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RCS\Util\ReflectionHelper;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\SimpleCache\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use RCS\Cache\StringIntBiDiMap;

#[CoversClass(Formidable::class)]
#[UsesClass(ReflectionHelper::class)]
class FormidableTest extends TestCase
{
    const FORM_KEY = 'formKey';
    const FIELD_KEY = 'fieldKey';
    const VIEW_KEY = 'viewKey';

    private const OPTION_1_LABEL = 'Test Option 1 Label';
    private const OPTION_1_VALUE = 'Test Option 1 Value';
    private const OPTION_2_LABEL = 'Test Option 2 Label';
    private const OPTION_2_VALUE = 2;

    private static LegacyMockInterface&MockInterface $mockFrmForm;
    private static LegacyMockInterface&MockInterface $mockFrmField;
    private static LegacyMockInterface&MockInterface $mockFrmViewsDisplay;

    private static int $testId = 0;
    private static ?string $testKey = null;

    private static \stdClass $testEntry;

    private MockObject $mockCache;

    private MockObject $mockFieldMap;
    private MockObject $mockFormMap;
    private MockObject $mockViewMap;

    /**
     * Set the identifier to be returned by the mock object
     *
     * @param int $id An identifier
     */
    private function setTestId(int $id): void
    {
        self::$testId = $id;
    }

    private function setTestKey(?string $key): void
    {
        self::$testKey = $key;
    }

    private static function initTestEntry(): void
    {
        $options = [
            [
                'label' => self::OPTION_1_LABEL,
                'value' => self::OPTION_1_VALUE
            ],
            [
                'label' => self::OPTION_2_LABEL,
                'value' => self::OPTION_2_VALUE
            ]
        ];

        self::$testEntry = new \stdClass();
        self::$testEntry->options = $options;
    }


    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUpBeforeClass()
     */
    public static function setUpBeforeClass(): void
    {
        /* Check if the actual Formidable Forms are available. If so, we
         * can't use our test classes.
         */
//         if (class_exists(\FrmField::class)) {
            self::initTestEntry();

            self::$mockFrmForm = \Mockery::mock('overload:\FrmForm');
            self::$mockFrmForm->shouldReceive('get_id_by_key')->andReturnUsing(fn() => self::$testId);
            self::$mockFrmForm->shouldReceive('get_key_by_id')->andReturnUsing(fn() => self::$testKey);

            self::$mockFrmField = \Mockery::mock('overload:\FrmField');
            self::$mockFrmField->shouldReceive('get_id_by_key')->andReturnUsing(fn() => self::$testId);
            self::$mockFrmField->shouldReceive('get_key_by_id')->andReturnUsing(fn() => self::$testKey);
            self::$mockFrmField->shouldReceive('getOne')->andReturnUsing(fn() => self::$testEntry);

            self::$mockFrmViewsDisplay = \Mockery::mock('overload:\FrmViewsDisplay');
            self::$mockFrmViewsDisplay->shouldReceive('get_id_by_key')->andReturnUsing(fn() => self::$testId);
            self::$mockFrmViewsDisplay->shouldReceive('get_key_by_id')->andReturnUsing(fn() => self::$testKey);
            //         } else {
//             self::markTestSkipped('Real Formidable classes are available, unable to test');
//         }
    }

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        $this->mockCache = $this->createMock(CacheInterface::class);
        $this->mockCache->method('set')->willReturn(true);

        $this->mockFieldMap = $this->createMock(StringIntBiDiMap::class);
        $this->mockFormMap = $this->createMock(StringIntBiDiMap::class);
        $this->mockViewMap = $this->createMock(StringIntBiDiMap::class);

        ReflectionHelper::setClassProperty(
            Formidable::class,
            'maps',
            [
                FormidableClassEnum::Field->value => $this->mockFieldMap,
                FormidableClassEnum::Form->value => $this->mockFormMap,
                FormidableClassEnum::View->value => $this->mockViewMap
            ]
            );
    }

    /************************************************************************
     * FrmForm Tests
     ************************************************************************/
    public function testGetFormId_missingId (): void
    {
        $this->setTestId(0);

        $this->mockFormMap->expects($this->once())->method('getInt')->with(self::FORM_KEY)->willReturn(null);
        $this->mockFormMap->expects($this->never())->method('set');

        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertNull($id);
    }

    public function testGetFormId_notCached (): void
    {
        $testId = 27;
        $this->setTestId($testId);

        $this->mockFormMap->expects($this->once())->method('getInt')->with(self::FORM_KEY)->willReturn(null);
        $this->mockFormMap->expects($this->once())->method('set')->with(self::FORM_KEY, $testId);

        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetFormId_cached (): void
    {
        $testId = 97;
        $this->setTestId($testId);

        $this->mockFormMap->expects($this->exactly(2))->method('getInt')->with(self::FORM_KEY)->willReturn($testId);
        $this->mockFormMap->expects($this->never())->method('set');

        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertEquals(self::$testId, $id);

        $this->setTestId(-1);   // Cached id should be returned
        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetFormKey_missingKey (): void
    {
        $testId = 23;
        $this->setTestKey(null);

        $this->mockFormMap->expects($this->once())->method('getString')->with($testId)->willReturn(null);
        $this->mockFormMap->expects($this->never())->method('set');

        $key = Formidable::getFormKey($testId);

        self::assertNull($key);
    }

    public function testGetFormKey_notCached (): void
    {
        $testId = 63;
        $this->setTestkey(self::FORM_KEY);

        $this->mockFormMap->expects($this->once())->method('getString')->with($testId)->willReturn(null);
        $this->mockFormMap->expects($this->once())->method('set')->with(self::FORM_KEY, $testId);

        $key = Formidable::getFormKey($testId);

        self::assertEquals(self::$testKey, $key);
    }

    public function testGetFormKey_cached (): void
    {
        $testId = 98;
        $this->setTestKey(self::FORM_KEY);

        $this->mockFormMap->expects($this->exactly(2))->method('getString')->with($testId)->willReturn(self::FORM_KEY);
        $this->mockFormMap->expects($this->never())->method('set');

        $key = Formidable::getFormKey($testId);

        self::assertEquals(self::FORM_KEY, $key);

        $this->setTestKey('InvalidKey');   // Cached id should be returned
        $key = Formidable::getFormKey($testId);

        self::assertEquals(self::FORM_KEY, $key);
    }

    /************************************************************************
     * FrmField Tests
     ************************************************************************/
    public function testGetFieldId_missingId (): void
    {
        $this->setTestId(0);

        $this->mockFieldMap->expects($this->once())->method('getInt')->with(self::FIELD_KEY)->willReturn(null);
        $this->mockFieldMap->expects($this->never())->method('set');

        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertNull($id);
    }

    public function testGetFieldId_notCached (): void
    {
        $testId = 39;
        $this->setTestId($testId);

        $this->mockFieldMap->expects($this->once())->method('getInt')->with(self::FIELD_KEY)->willReturn(null);
        $this->mockFieldMap->expects($this->once())->method('set')->with(self::FIELD_KEY, $testId);

        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetFieldId_cached (): void
    {
        $testId = 79;
        $this->setTestId($testId);

        $this->mockFieldMap->expects($this->exactly(2))->method('getInt')->with(self::FIELD_KEY)->willReturn($testId);
        $this->mockFormMap->expects($this->never())->method('set');

        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertEquals($testId, $id);

        $this->setTestId(-1);   // Cached id should be returned
        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetFieldKey_missingKey (): void
    {
        $testId = 48;
        $this->setTestKey(null);

        $this->mockFieldMap->expects($this->once())->method('getString')->with($testId)->willReturn(null);
        $this->mockFieldMap->expects($this->never())->method('set');

        $key = Formidable::getFieldKey($testId);

        self::assertNull($key);
    }

    public function testGetFieldKey_notCached (): void
    {
        $testId = 39;
        $this->setTestKey(self::FIELD_KEY);

        $this->mockFieldMap->expects($this->once())->method('getString')->with($testId)->willReturn(null);
        $this->mockFieldMap->expects($this->once())->method('set')->with(self::FIELD_KEY, $testId);

        $key = Formidable::getFieldKey($testId);

        self::assertEquals(self::$testKey, $key);
    }

    public function testGetFieldKeyId_cached (): void
    {
        $testId = 71;
        $this->setTestKey(self::FIELD_KEY);

        $this->mockFieldMap->expects($this->exactly(2))->method('getString')->with($testId)->willReturn(self::FIELD_KEY);
        $this->mockFormMap->expects($this->never())->method('set');

        $key = Formidable::getFieldKey($testId);

        self::assertEquals(self::FIELD_KEY, $key);

        $this->setTestKey('DifferentKey');   // Cached id should be returned
        $key = Formidable::getFieldKey($testId);

        self::assertEquals(self::FIELD_KEY, $key);
    }

    /************************************************************************
     * FrmView Tests
     ************************************************************************/
    public function testGetViewId_missingId (): void
    {
        $this->setTestId(0);

        $this->mockViewMap->expects($this->once())->method('getInt')->with(self::VIEW_KEY)->willReturn(null);
        $this->mockViewMap->expects($this->never())->method('set');

        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertNull($id);
    }

    public function testGetViewId_notCached (): void
    {
        $testId = 72;
        $this->setTestId($testId);

        $this->mockViewMap->expects($this->once())->method('getInt')->with(self::VIEW_KEY)->willReturn(null);
        $this->mockViewMap->expects($this->once())->method('set')->with(self::VIEW_KEY, $testId);

        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertEquals(self::$testId, $id);
    }

    public function testGetViewId_cached (): void
    {
        $testId = 82;
        $this->setTestId($testId);

        $this->mockViewMap->expects($this->exactly(2))->method('getInt')->with(self::VIEW_KEY)->willReturn($testId);
        $this->mockViewMap->expects($this->never())->method('set');

        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertEquals($testId, $id);

        $this->setTestId(-1);   // Cached id should be returned
        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetViewKey_missingKey (): void
    {
        $testId = 48;
        $this->setTestKey(null);

        $this->mockViewMap->expects($this->once())->method('getString')->with($testId)->willReturn(null);
        $this->mockViewMap->expects($this->never())->method('set');

        $key = Formidable::getViewKey($testId);

        self::assertNull($key);
    }

    public function testGetViewKey_notCached (): void
    {
        $testId = 72;
        $this->setTestKey(self::VIEW_KEY);

        $this->mockViewMap->expects($this->once())->method('getString')->with($testId)->willReturn(null);
        $this->mockViewMap->expects($this->once())->method('set')->with(self::VIEW_KEY, $testId);

        $key = Formidable::getViewKey($testId);

        self::assertEquals(self::$testKey, $key);
    }

    public function testGetViewKey_cached (): void
    {
        $testId = 85;
        $this->setTestKey(self::VIEW_KEY);

        $this->mockViewMap->expects($this->exactly(2))->method('getString')->with($testId)->willReturn(self::$testKey);
        $this->mockViewMap->expects($this->never())->method('set');

        $key = Formidable::getViewKey($testId);

        self::assertEquals(self::VIEW_KEY, $key);

        $this->setTestKey('AnotherKey');   // Cached id should be returned
        $key = Formidable::getViewKey($testId);

        self::assertEquals(self::VIEW_KEY, $key);
    }

    public function testGetAllId_unique (): void
    {
        $key = 'SameKey';

        $testFormId = 77;
        $testFieldId = 88;
        $testViewId = 99;

        $this->mockFieldMap->method('getInt')->willReturnOnConsecutiveCalls(null, $testFieldId);
        $this->mockFormMap->method('getInt')->willReturnOnConsecutiveCalls(null, $testFormId);
        $this->mockViewMap->method('getInt')->willReturnOnConsecutiveCalls(null, $testViewId);

        $this->mockFieldMap->expects($this->once())->method('set')->with($key, $testFieldId);
        $this->mockFormMap->expects($this->once())->method('set')->with($key, $testFormId);
        $this->mockViewMap->expects($this->once())->method('set')->with($key, $testViewId);

        $this->setTestId($testFormId);
        $formId = Formidable::getFormId($key);

        $this->setTestId($testFieldId);
        $fieldId = Formidable::getFieldId($key);

        $this->setTestId($testViewId);
        $viewId = Formidable::getViewId($key);

        self::assertEquals($testFormId, $formId);
        self::assertEquals($testFieldId, $fieldId);
        self::assertEquals($testViewId, $viewId);

        $this->setTestId(-1);

        // And again to hit the cached values
        $formId = Formidable::getFormId($key);
        $fieldId = Formidable::getFieldId($key);
        $viewId = Formidable::getViewId($key);

        self::assertEquals($testFormId, $formId);
        self::assertEquals($testFieldId, $fieldId);
        self::assertEquals($testViewId, $viewId);
    }

    /************************************************************************
     * Get Label/Value Tests
     ************************************************************************/
    public function testGetFieldOptionLabel_string(): void
    {
        $label = Formidable::getFieldOptionLabel(0, self::OPTION_1_VALUE);

        self::assertNotEmpty($label);
        self::assertEquals(self::OPTION_1_LABEL, $label);
    }

    public function testGetFieldOptionLabel_int(): void
    {
        $label = Formidable::getFieldOptionLabel(0, self::OPTION_2_VALUE);

        self::assertNotEmpty($label);
        self::assertEquals(self::OPTION_2_LABEL, $label);
    }

    public function testGetFieldOptionValue_string(): void
    {
        $label = Formidable::getFieldOptionValue(0, self::OPTION_1_LABEL);

        self::assertNotEmpty($label);
        self::assertIsString($label);
        self::assertEquals(self::OPTION_1_VALUE, $label);
    }

    public function testGetFieldOptionValue_int(): void
    {
        $label = Formidable::getFieldOptionValue(0, self::OPTION_2_LABEL);

        self::assertNotEmpty($label);
        self::assertIsNumeric($label);
        self::assertEquals(self::OPTION_2_VALUE, $label);
    }

    public function testDisableAndRestoreDbCacheStoresState(): void
    {
        global $frm_vars;
        $frm_vars = ['prevent_caching' => false];

        Formidable::disableDbCache();
        $this->assertTrue($frm_vars['prevent_caching']);    // @phpstan-ignore method.impossibleType (set to true be Formidable class)

        Formidable::restoreDbCache();
        $this->assertFalse($frm_vars['prevent_caching']);
    }
}
