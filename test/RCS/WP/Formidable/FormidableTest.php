<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

require_once __DIR__ . '/../../../Fixtures/FormidableStubs.php';

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RCS\WP\Formidable\Formidable;

#[CoversClass(Formidable::class)]
final class FormidableTest extends TestCase
{
    private const FLAG = 'prevent_caching';

    protected function setUp(): void
    {
        // The class under test keeps its state in private statics, so wipe
        // them between tests to keep tests independent.
        $this->resetStatic('formIdCache', []);
        $this->resetStatic('fieldIdCache', []);
        $this->resetStatic('viewIdCache', []);
        $this->resetStatic('dbCacheState', []);

        \FrmForm::reset();
        \FrmField::reset();
        \FrmField::$fields = [];
        \FrmViewsDisplay::reset();

        $GLOBALS['frm_vars'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['frm_vars']);
    }

    private function resetStatic(string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty(Formidable::class, $property);
        $ref->setValue(null, $value);
    }

    private function getStatic(string $property): mixed
    {
        return (new \ReflectionProperty(Formidable::class, $property))->getValue();
    }

    // ------------------------------------------------------------------
    // ID lookups (form, field, view)
    // ------------------------------------------------------------------

    /** @return array<string, array{string, string}> [method, stub class] */
    public static function idLookupProvider(): array
    {
        return [
            'form'  => ['getFormId',  \FrmForm::class],
            'field' => ['getFieldId', \FrmField::class],
            'view'  => ['getViewId',  \FrmViewsDisplay::class],
        ];
    }

    #[DataProvider('idLookupProvider')]
    public function testGetIdReturnsIdForKnownKey(string $method, string $stub): void
    {
        $stub::$idsByKey = ['my_key' => 42];

        $this->assertSame(42, Formidable::$method('my_key'));
    }

    #[DataProvider('idLookupProvider')]
    public function testGetIdCastsNumericStringToInt(string $method, string $stub): void
    {
        // Formidable returns ids from $wpdb as strings.
        $stub::$idsByKey = ['my_key' => 17];

        $this->assertSame(17, Formidable::$method('my_key'));
    }

    #[DataProvider('idLookupProvider')]
    public function testGetIdReturnsNullForUnknownKey(string $method, string $stub): void
    {
        $this->assertNull(Formidable::$method('does_not_exist'));
    }

    #[DataProvider('idLookupProvider')]
    public function testGetIdCachesSuccessfulLookups(string $method, string $stub): void
    {
        $stub::$idsByKey = ['my_key' => 42];

        Formidable::$method('my_key');
        Formidable::$method('my_key');
        Formidable::$method('my_key');

        $this->assertSame(1, $stub::$idCalls);
    }

    #[DataProvider('idLookupProvider')]
    public function testGetIdDoesNotCacheMisses(string $method, string $stub): void
    {
        $this->assertNull(Formidable::$method('late_key'));

        // The entry is created after the first miss; it should now be found.
        $stub::$idsByKey = ['late_key' => 9];

        $this->assertSame(9, Formidable::$method('late_key'));
        $this->assertSame(2, $stub::$idCalls);
    }

    #[DataProvider('idLookupProvider')]
    public function testGetIdCachesEachKeySeparately(string $method, string $stub): void
    {
        $stub::$idsByKey = ['a' => 1, 'b' => 2];

        $this->assertSame(1, Formidable::$method('a'));
        $this->assertSame(2, Formidable::$method('b'));
        $this->assertSame(1, Formidable::$method('a'));
        $this->assertSame(2, $stub::$idCalls);
    }

    // ------------------------------------------------------------------
    // Key lookups (form, field)
    // ------------------------------------------------------------------

    /** @return array<string, array{string, string, string}> [idMethod, keyMethod, stub class] */
    public static function keyLookupProvider(): array
    {
        return [
            'form'  => ['getFormId',  'getFormKey',  \FrmForm::class],
            'field' => ['getFieldId', 'getFieldKey', \FrmField::class],
        ];
    }

    #[DataProvider('keyLookupProvider')]
    public function testGetKeyReturnsKeyForKnownId(string $idMethod, string $keyMethod, string $stub): void
    {
        $stub::$keysById = [42 => 'my_key'];

        $this->assertSame('my_key', Formidable::$keyMethod(42));
    }

    #[DataProvider('keyLookupProvider')]
    public function testGetKeyReturnsNullForUnknownId(string $idMethod, string $keyMethod, string $stub): void
    {
        $this->assertNull(Formidable::$keyMethod(999));
    }

    #[DataProvider('keyLookupProvider')]
    public function testGetKeyCachesSuccessfulLookups(string $idMethod, string $keyMethod, string $stub): void
    {
        $stub::$keysById = [42 => 'my_key'];

        Formidable::$keyMethod(42);
        Formidable::$keyMethod(42);

        $this->assertSame(1, $stub::$keyCalls);
    }

    #[DataProvider('keyLookupProvider')]
    public function testGetKeyDoesNotCacheMisses(string $idMethod, string $keyMethod, string $stub): void
    {
        $this->assertNull(Formidable::$keyMethod(5));

        $stub::$keysById = [5 => 'late_key'];

        $this->assertSame('late_key', Formidable::$keyMethod(5));
        $this->assertSame(2, $stub::$keyCalls);
    }

    #[DataProvider('keyLookupProvider')]
    public function testGetKeyUsesCachePopulatedByGetId(string $idMethod, string $keyMethod, string $stub): void
    {
        $stub::$idsByKey = ['my_key' => 42];

        Formidable::$idMethod('my_key');

        $this->assertSame('my_key', Formidable::$keyMethod(42));
        $this->assertSame(0, $stub::$keyCalls, 'Key should come from the cache, not Formidable');
    }

    #[DataProvider('keyLookupProvider')]
    public function testGetIdUsesCachePopulatedByGetKey(string $idMethod, string $keyMethod, string $stub): void
    {
        $stub::$keysById = [42 => 'my_key'];

        Formidable::$keyMethod(42);

        $this->assertSame(42, Formidable::$idMethod('my_key'));
        $this->assertSame(0, $stub::$idCalls, 'Id should come from the cache, not Formidable');
    }

    public function testFormAndFieldCachesAreIndependent(): void
    {
        \FrmForm::$idsByKey  = ['shared_key' => 1];
        \FrmField::$idsByKey = ['shared_key' => 2];

        $this->assertSame(1, Formidable::getFormId('shared_key'));
        $this->assertSame(2, Formidable::getFieldId('shared_key'));
    }

    // ------------------------------------------------------------------
    // View keys.
    // NOTE: Formidable::getViewKey() is missing a `return` statement, so
    // these tests FAIL (TypeError: none returned) until that is fixed.
    // ------------------------------------------------------------------

    public function testGetViewKeyReturnsKeyForKnownId(): void
    {
        \FrmViewsDisplay::$keysById = [7 => 'my_view'];

        $this->assertSame('my_view', Formidable::getViewKey(7));
    }

    public function testGetViewKeyReturnsNullForUnknownId(): void
    {
        $this->assertNull(Formidable::getViewKey(999));
    }

    public function testGetViewKeyIsCachedAndSharedWithGetViewId(): void
    {
        \FrmViewsDisplay::$keysById = [7 => 'my_view'];

        Formidable::getViewKey(7);

        $this->assertSame(7, Formidable::getViewId('my_view'));
        $this->assertSame(0, \FrmViewsDisplay::$idCalls);
    }

    // ------------------------------------------------------------------
    // disableDbCache() / restoreDbCache()
    // ------------------------------------------------------------------

    public function testDisableDbCacheSetsFlag(): void
    {
        Formidable::disableDbCache();

        $this->assertTrue($GLOBALS['frm_vars'][self::FLAG]);
    }

    public function testRestoreRemovesFlagWhenItWasNotSetBefore(): void
    {
        Formidable::disableDbCache();
        Formidable::restoreDbCache();

        $this->assertArrayNotHasKey(self::FLAG, $GLOBALS['frm_vars']);
    }

    public function testRestoreReinstatesPreviousFalseValue(): void
    {
        $GLOBALS['frm_vars'][self::FLAG] = false;

        Formidable::disableDbCache();
        $this->assertTrue($GLOBALS['frm_vars'][self::FLAG]);

        Formidable::restoreDbCache();
        $this->assertFalse($GLOBALS['frm_vars'][self::FLAG]);
    }

    public function testRestoreReinstatesPreviousTrueValue(): void
    {
        $GLOBALS['frm_vars'][self::FLAG] = true;

        Formidable::disableDbCache();
        Formidable::restoreDbCache();

        $this->assertTrue($GLOBALS['frm_vars'][self::FLAG]);
    }

    public function testNestedDisableAndRestore(): void
    {
        Formidable::disableDbCache();
        Formidable::disableDbCache();

        Formidable::restoreDbCache();
        $this->assertTrue($GLOBALS['frm_vars'][self::FLAG], 'Still disabled after inner restore');

        Formidable::restoreDbCache();
        $this->assertArrayNotHasKey(self::FLAG, $GLOBALS['frm_vars'], 'Fully restored after outer restore');
    }

    public function testRestoreWithoutDisableDoesNothing(): void
    {
        $GLOBALS['frm_vars'][self::FLAG] = false;

        Formidable::restoreDbCache();

        $this->assertFalse($GLOBALS['frm_vars'][self::FLAG]);
        $this->assertSame([], $this->getStatic('dbCacheState'));
    }

    public function testRestoreWithUnrelatedFrmVarsLeavesThemAlone(): void
    {
        $GLOBALS['frm_vars']['other'] = 'keep me';

        Formidable::disableDbCache();
        Formidable::restoreDbCache();

        $this->assertSame('keep me', $GLOBALS['frm_vars']['other']);
    }

    public function testDisableResetsStateWhenFlagWasRemovedExternally(): void
    {
        Formidable::disableDbCache();
        Formidable::disableDbCache();            // state: [-1, true]
        unset($GLOBALS['frm_vars'][self::FLAG]); // Formidable clears the flag

        Formidable::disableDbCache();            // state should restart: [-1]
        $this->assertSame([-1], $this->getStatic('dbCacheState'));

        Formidable::restoreDbCache();
        $this->assertArrayNotHasKey(self::FLAG, $GLOBALS['frm_vars']);
    }

    // ------------------------------------------------------------------
    // Field options
    // ------------------------------------------------------------------

    /** @param array<int, array{value: string|int, label: string}> $options */
    private function givenFieldWithOptions(int $fieldId, array $options): void
    {
        $field = new \stdClass();
        $field->options = $options;
        \FrmField::$fields[$fieldId] = $field;
    }

    public function testGetFieldOptionsReturnsOptions(): void
    {
        $options = [
            ['value' => 'a', 'label' => 'Alpha'],
            ['value' => 'b', 'label' => 'Beta'],
        ];
        $this->givenFieldWithOptions(10, $options);

        $this->assertSame($options, Formidable::getFieldOptions(10));
    }

    public function testGetFieldOptionsReturnsEmptyArrayForMissingField(): void
    {
        $this->assertSame([], Formidable::getFieldOptions(404));
    }

    public function testGetFieldOptionsReturnsEmptyArrayWhenFieldHasNoOptionsProperty(): void
    {
        \FrmField::$fields[10] = new \stdClass();

        $this->assertSame([], Formidable::getFieldOptions(10));
    }

    public function testGetFieldOptionsReturnsEmptyArrayWhenOptionsIsNull(): void
    {
        $field = new \stdClass();
        $field->options = null;
        \FrmField::$fields[10] = $field;

        $this->assertSame([], Formidable::getFieldOptions(10));
    }

    public function testGetFieldOptionLabelReturnsMatchingLabel(): void
    {
        $this->givenFieldWithOptions(10, [
            ['value' => 'a', 'label' => 'Alpha'],
            ['value' => 'b', 'label' => 'Beta'],
        ]);

        $this->assertSame('Beta', Formidable::getFieldOptionLabel(10, 'b'));
    }

    public function testGetFieldOptionLabelReturnsEmptyStringWhenNoMatch(): void
    {
        $this->givenFieldWithOptions(10, [['value' => 'a', 'label' => 'Alpha']]);

        $this->assertSame('', Formidable::getFieldOptionLabel(10, 'zzz'));
    }

    public function testGetFieldOptionLabelReturnsEmptyStringForMissingField(): void
    {
        $this->assertSame('', Formidable::getFieldOptionLabel(404, 'a'));
    }

    public function testGetFieldOptionLabelMatchesIntAgainstNumericStringValue(): void
    {
        $this->givenFieldWithOptions(10, [['value' => '2', 'label' => 'Two']]);

        $this->assertSame('Two', Formidable::getFieldOptionLabel(10, 2));
    }

    public function testGetFieldOptionLabelMatchesStringAgainstIntValue(): void
    {
        $this->givenFieldWithOptions(10, [['value' => 2, 'label' => 'Two']]);

        $this->assertSame('Two', Formidable::getFieldOptionLabel(10, '2'));
    }

    public function testGetFieldOptionLabelReturnsFirstMatchWhenValuesAreDuplicated(): void
    {
        $this->givenFieldWithOptions(10, [
            ['value' => 'x', 'label' => 'First'],
            ['value' => 'x', 'label' => 'Second'],
        ]);

        $this->assertSame('First', Formidable::getFieldOptionLabel(10, 'x'));
    }

    public function testGetFieldOptionValueReturnsMatchingValue(): void
    {
        $this->givenFieldWithOptions(10, [
            ['value' => 'a', 'label' => 'Alpha'],
            ['value' => 'b', 'label' => 'Beta'],
        ]);

        $this->assertSame('b', Formidable::getFieldOptionValue(10, 'Beta'));
    }

    public function testGetFieldOptionValuePreservesIntegerValues(): void
    {
        $this->givenFieldWithOptions(10, [['value' => 5, 'label' => 'Five']]);

        $this->assertSame(5, Formidable::getFieldOptionValue(10, 'Five'));
    }

    public function testGetFieldOptionValueReturnsEmptyStringWhenNoMatch(): void
    {
        $this->givenFieldWithOptions(10, [['value' => 'a', 'label' => 'Alpha']]);

        $this->assertSame('', Formidable::getFieldOptionValue(10, 'Nope'));
    }

    public function testGetFieldOptionValueReturnsEmptyStringForMissingField(): void
    {
        $this->assertSame('', Formidable::getFieldOptionValue(404, 'Alpha'));
    }

    public function testGetFieldOptionValueIsCaseSensitive(): void
    {
        $this->givenFieldWithOptions(10, [['value' => 'a', 'label' => 'Alpha']]);

        $this->assertSame('', Formidable::getFieldOptionValue(10, 'alpha'));
    }

    public function testGetFieldOptionValueReturnsFirstMatchWhenLabelsAreDuplicated(): void
    {
        $this->givenFieldWithOptions(10, [
            ['value' => 'one', 'label' => 'Dup'],
            ['value' => 'two', 'label' => 'Dup'],
        ]);

        $this->assertSame('one', Formidable::getFieldOptionValue(10, 'Dup'));
    }
}
