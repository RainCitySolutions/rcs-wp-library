<?php
declare(strict_types=1);
namespace RCS\WP\Settings;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Fixtures\TestAdminSettingsTab;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\LoggerInterface;
use RCS\WP\PluginOptionsInterface;
use RCS\WP\Validation\BaseValidator;
use RCS\WP\Validation\EmailValidator;
use RCS\WP\Validation\NumberValidator;
use RCS\WP\Validation\StringValidator;

#[CoversClass(AdminSettingsTab::class)]
#[UsesClass(FormFieldInfo::class)]
#[UsesClass(BaseValidator::class)]
#[UsesClass(EmailValidator::class)]
#[UsesClass(NumberValidator::class)]
#[UsesClass(StringValidator::class)]
final class AdminSettingsTabTest extends TestCase
{
    protected function setUp(): void
    {
        Monkey\setUp();

        // Basic stubs for common WordPress functions
        Functions\when('esc_attr')->alias(fn($v) => htmlspecialchars((string)$v, ENT_QUOTES));
        Functions\when('wp_editor')->justReturn('<textarea>RTE</textarea>');
        Functions\when('printf')->alias('printf');
        Functions\when('get_class')->alias('get_class');
        Functions\when('add_settings_error')->justReturn();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
    }

    private function makeTab(?PluginOptionsInterface $options = null, ?callable $optionsExpectation = null): TestAdminSettingsTab
    {
        $options ??= $this->createMock(PluginOptionsInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        if (!is_null($optionsExpectation)) {
            $optionsExpectation($options);
        }

        return new TestAdminSettingsTab('General', $options, $logger);
    }

    #[Test]
    public function it_generates_deterministic_tab_id(): void
    {
        $tab1 = $this->makeTab();
        $tab2 = $this->makeTab();
        $this->assertSame($tab1->getIdPublic(), $tab2->getIdPublic());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $tab1->getIdPublic());
    }

    #[Test]
    public function it_returns_tab_name(): void
    {
        $tab = $this->makeTab();
        $this->assertSame('General', $tab->getNamePublic());
    }

    #[Test]
    public function it_registers_setting_and_calls_addSettings(): void
    {
        $tab = $this->makeTab();

        $called = false;
        Functions\expect('register_setting')
        ->once()
        ->andReturnUsing(function ($slug, $name, $args) use (&$called) {
            $called = is_callable($args['sanitize_callback']);
            return true;
        });

        $tab->exposeInit('plugin_page');
        $this->assertTrue($called, 'register_setting should receive sanitize callback');
        $this->assertNotEmpty($tab->called['addSettings']);
    }

    #[Test]
    public function it_runs_sanitize_callback_when_registered(): void
    {
        Functions\when('register_setting')->justReturn(true);

        $tab = $this->makeTab();
        $tab->exposeInit('my-page');
        $cb = $tab->called['addSettings']; // ensures it ran
        $this->assertNotEmpty($cb);
    }

    #[Test]
    public function it_renders_text_field_html(): void
    {
        $options = $this->createMock(PluginOptionsInterface::class);
        $options->method('isValidKey')->willReturn(true);
        $options->method('getOptionName')->willReturn('opts');
        $options->method('getValue')->willReturn('abc');

        $tab = $this->makeTab($options);

        $html = $tab->renderTextPublic('field1', 'Some description');
        $this->assertStringContainsString('input', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('description', $html);
        $this->assertStringContainsString('abc', $html);
    }

    #[Test]
    public function getFormFieldInfo_returns_null_if_invalid_key(): void
    {
        $options = $this->createMock(PluginOptionsInterface::class);
        $options->method('isValidKey')->willReturn(false);
        $tab = $this->makeTab($options);

        $info = $tab->callGetFormFieldInfo('badkey');
        $this->assertNull($info);
    }

    #[Test]
    public function getFormFieldInfo_returns_form_field_info_if_valid(): void
    {
        $options = $this->createMock(PluginOptionsInterface::class);
        $options->method('isValidKey')->willReturn(true);
        $options->method('getOptionName')->willReturn('settings');
        $options->method('getValue')->willReturn('123');
        $tab = $this->makeTab($options);

        $info = $tab->callGetFormFieldInfo('mykey');
        $this->assertInstanceOf(FormFieldInfo::class, $info);
        $this->assertSame('settings[mykey]', $info->fieldName);
        $this->assertSame('123', $info->fieldValue);
    }

    #[Test]
    public function it_renders_checkbox_matrix_field(): void
    {
        $options = $this->createMock(PluginOptionsInterface::class);
        $options->method('isValidKey')->willReturn(true);
        $options->method('getOptionName')->willReturn('opts');
        $options->method('getValue')->willReturn('1');
        $tab = $this->makeTab($options);

        $entry1 = new CheckboxMatrixEntry();
        $entry1->name = 'Choice A';
        $entry1->value = 'a';
        $entry1->isSelected = true;

        $entry2 = new CheckboxMatrixEntry();
        $entry2->name = 'Choice B';
        $entry2->value = 'b';
        $entry2->isSelected = false;

        ob_start();
        $tabReflection = new \ReflectionClass($tab);
        $method = $tabReflection->getMethod('renderCheckboxMatrixField');
        $method->setAccessible(true);
        $method->invoke($tab, 'myoption', 'desc', [$entry1, $entry2]);
        $html = ob_get_clean();

        $this->assertStringContainsString('Choice A', $html);
        $this->assertStringContainsString('Choice B', $html);
        $this->assertStringContainsString('<table', $html);
    }

//     #[Test]
//     public function validate_string_value_sets_option_when_valid(): void
//     {
//         $tab = $this->makeTab(
//             null,
//             function (PluginOptionsInterface $options): void {
//                 $options
//                 ->expects(self::once())
//                 ->method('setValue')
//                 ->with('username', 'John');
//             }
//             );

//         $tab->validateString(
//             'username',
//             ' John ',
//             'test-page',
//             'Username'
//             );
//     }

//     #[Test]
//     public function validate_string_value_does_not_set_option_when_invalid(): void
//     {
//         $tab = $this->makeTab(
//             null,
//             function (PluginOptionsInterface $options): void {
//                 $options
//                 ->expects(self::never())
//                 ->method('setValue');
//             }
//             );

//         $tab->validateString(
//             'username',
//             '',
//             'test-page',
//             'Username'
//             );
//     }

//     #[Test]
//     public function validate_email_sets_option_when_valid(): void
//     {
//         $tab = $this->makeTab(
//             null,
//             function (PluginOptionsInterface $options): void {
//                 $options
//                 ->expects(self::once())
//                 ->method('setValue')
//                 ->with('email', 'test@example.com');
//             }
//             );

//         $tab->validateEmail(
//             'email',
//             ' test@example.com ',
//             'test-page',
//             'Email'
//             );
//     }

//     #[Test]
//     public function validate_email_does_not_set_option_when_invalid(): void
//     {
//         $tab = $this->makeTab(
//             null,
//             function (PluginOptionsInterface $options): void {
//                 $options
//                 ->expects(self::never())
//                 ->method('setValue');
//             }
//             );

//         $tab->validateEmail(
//             'email',
//             'not-an-email',
//             'test-page',
//             'Email'
//             );
//     }

//     #[Test]
//     public function validate_numeric_sets_option_when_within_range(): void
//     {
//         $tab = $this->makeTab(
//             null,
//             function (PluginOptionsInterface $options): void {
//                 $options
//                 ->expects(self::once())
//                 ->method('setValue')
//                 ->with('limit', '10');
//             }
//             );

//         $tab->validateNumber(
//             'limit',
//             '10',
//             'test-page',
//             'Limit',
//             1,
//             20
//             );
//     }

//    #[Test]
//     public function validate_numeric_does_not_set_option_when_out_of_range(): void
//     {
//         $tab = $this->makeTab(
//             null,
//             function (PluginOptionsInterface $options): void {
//                 $options
//                 ->expects(self::never())
//                 ->method('setValue');
//             }
//             );

//         $tab->validateNumber(
//             'limit',
//             '100',
//             'test-page',
//             'Limit',
//             1,
//             20,
//             'Out of range'
//             );
//     }
}
