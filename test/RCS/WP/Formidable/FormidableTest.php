<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RCS\Util\ReflectionHelper;
use PHPUnit\Framework\Attributes\UsesClass;

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

    private static \stdClass $testEntry;

    /**
     * Set the identifier to be returned by the mock object
     *
     * @param int $id An identifier
     */
    private function setTestId(int $id): void
    {
        self::$testId = $id;
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

            self::$mockFrmField = \Mockery::mock('overload:\FrmField');
            self::$mockFrmField->shouldReceive('get_id_by_key')->andReturnUsing(fn() => self::$testId);
            self::$mockFrmField->shouldReceive('getOne')->andReturnUsing(fn() => self::$testEntry);

            self::$mockFrmViewsDisplay = \Mockery::mock('overload:\FrmViewsDisplay');
            self::$mockFrmViewsDisplay->shouldReceive('get_id_by_key')->andReturnUsing(fn() => self::$testId);
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
        $this->resetFormidableCache('formIdCache');
        $this->resetFormidableCache('fieldIdCache');
        $this->resetFormidableCache('viewIdCache');
    }

    private function resetFormidableCache(string $cacheField): void
    {
        ReflectionHelper::setClassProperty(__NAMESPACE__.'\Formidable', $cacheField, []);
    }

    private function assertCacheEmpty(string $cacheField): void
    {
        self::assertEmpty(
            ReflectionHelper::getClassProperty(__NAMESPACE__.'\Formidable', $cacheField)
            );
    }

    /************************************************************************
     * FrmForm Tests
     ************************************************************************/
    public function testGetFormId_missingId (): void
    {
        $this->setTestId(0);

        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertNull($id);
    }

    public function testGetFormId_notCached (): void
    {
        $testId = 27;
        $this->setTestId($testId);

        self::assertCacheEmpty('formIdCache');

        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetFormId_cached (): void
    {
        $testId = 97;
        $this->setTestId($testId);

        self::assertCacheEmpty('formIdCache');
        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertEquals(self::$testId, $id);

        $this->setTestId(-1);   // Cached id should be returned
        $id = Formidable::getFormId(self::FORM_KEY);

        self::assertEquals($testId, $id);
    }

    /************************************************************************
     * FrmField Tests
     ************************************************************************/
    public function testGetFieldId_missingId (): void
    {
        $this->setTestId(0);

        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertNull($id);
    }

    public function testGetFieldId_notCached (): void
    {
        $testId = 39;
        $this->setTestId($testId);

        self::assertCacheEmpty('fieldIdCache');
        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetFieldId_cached (): void
    {
        $testId = 79;
        $this->setTestId($testId);

        self::assertCacheEmpty('fieldIdCache');
        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertEquals($testId, $id);

        $this->setTestId(-1);   // Cached id should be returned
        $id = Formidable::getFieldId(self::FIELD_KEY);

        self::assertEquals($testId, $id);
    }

    /************************************************************************
     * FrmView Tests
     ************************************************************************/
    public function testGetViewId_missingId (): void
    {
        $this->setTestId(0);

        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertNull($id);
    }

    public function testGetViewId_notCached (): void
    {
        $testId = 72;
        $this->setTestId($testId);

        self::assertCacheEmpty('viewIdCache');
        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertEquals(self::$testId, $id);
    }

    public function testGetViewId_cached (): void
    {
        $testId = 82;
        $this->setTestId($testId);

        self::assertCacheEmpty('viewIdCache');
        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertEquals($testId, $id);

        $this->setTestId(-1);   // Cached id should be returned
        $id = Formidable::getViewId(self::VIEW_KEY);

        self::assertEquals($testId, $id);
    }

    public function testGetAllId_unique (): void
    {
        $key = 'SameKey';

        $testFormId = 77;
        $testFieldId = 88;
        $testViewId = 99;

        self::assertCacheEmpty('formIdCache');
        self::assertCacheEmpty('fieldIdCache');
        self::assertCacheEmpty('viewIdCache');

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
