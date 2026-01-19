<?php
declare(strict_types=1);
namespace RCS\Util;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\RCS\Util\MinifyHelper::class)]
class MinifyHelperTest extends TestCase
{
    public function testMinifyHtml_noChange (): void
    {
        $input = '<button id="99">Button Text</button>';

        self::assertEquals($input, MinifyHelper::minifyHtml($input));
    }

    public function testMinifyHtml_whitespaceAfterTag (): void
    {
        $input = "<div> \t  <span>  Text</span>    </div>   ";
        $expected = '<div> <span> Text</span> </div>';

        self::assertEquals($expected, MinifyHelper::minifyHtml($input));
    }

    public function testMinifyHtml_whitespaceBeforeTag (): void
    {
        $input = "\t   <div>  \t\t  <span>Text    </span>   </div>";
        $expected = '<div> <span>Text </span> </div>';

        self::assertEquals($expected, MinifyHelper::minifyHtml($input));
    }

    public function testMinifyHtml_singleTagMultipleLines (): void
    {
        ob_start();
        ?>
        <button
            id="redcapConsent-99"
            class="btn btn-secondary btn-sm"
            type="button"
            data-redcap-url="http://test"
            data-redcap-code="1234567890ABCDEF"
            >
            Review/Revoke Consent
        </button>
        <?php
        $input = ob_get_contents();
        ob_end_clean();

        $expected =
            '<button id="redcapConsent-99" class="btn btn-secondary btn-sm" ' .
            'type="button" data-redcap-url="http://test" data-redcap-code="1234567890ABCDEF" > ' .
            'Review/Revoke Consent </button>';

        self::assertEquals($expected, MinifyHelper::minifyHtml($input));
    }
}
