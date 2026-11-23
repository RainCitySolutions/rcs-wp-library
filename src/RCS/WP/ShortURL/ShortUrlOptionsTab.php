<?php

declare(strict_types=1);

namespace RCS\WP\ShortURL;

use Psr\Log\LoggerInterface;
use RCS\Util\MinifyHelper;
use RCS\WP\Settings\AdminSettingsTab;

class ShortUrlOptionsTab
extends AdminSettingsTab
{
    private const TAB_NAME = "Short URLs";

    const OPTIONS_SECTION_MAPPINGS_ID = 'mappingsSection';
    const OPTIONS_SECTION_MAPPINGS_TITLE = 'URL Mappings';

    const AJAX_ADD_SHORT_URL = 'addShortUrlAction';
    const AJAX_DELETE_SHORT_URL = 'deleteShortUrlAction';

    public function __construct(
        ShortUrlOptions $options,
        private readonly ShortUrlHandler $handler,
        LoggerInterface $logger
    ) {
        parent::__construct(self::TAB_NAME, $options, $logger);

        add_action('wp_ajax_' . self::AJAX_ADD_SHORT_URL, array($this, 'ajaxAddUrl'));
        add_action('wp_ajax_' . self::AJAX_DELETE_SHORT_URL, array($this, 'ajaxDeleteUrl'));
    }

    public function addSettings(string $pageSlug): void
    {
        add_settings_section(
            self::OPTIONS_SECTION_MAPPINGS_ID,
            self::OPTIONS_SECTION_MAPPINGS_TITLE,
            function () {
?>
            <style>
                #rcsWpLibShortUrlTable table {
                    width: 100%;
                }

                #rcsWpLibShortUrlTable th:first-child {
                    width: 20%;
                    text-align: left;
                }

                #rcsWpLibShortUrlTable th:nth-child(2) {
                    width: 70%;
                    text-align: left;
                }

                #rcsWpLibShortUrlTable th:nth-child(3) {
                    width: 10%;
                    text-align: center;
                }

                #rcsWpLibShortUrlTable td:nth-child(3) {
                    text-align: center;
                }

                #rcsWpLibShortUrlTable .rcsWpLibShortUrlDeleteBtn svg {
                    height: 20px;
                }

                #rcsWpLibShortUrlCode {
                    width: 19%
                }

                #rcsWpLibShortUrlInput {
                    width: 69%
                }

                #rcsWpLibShortUrlAddBtn {
                    width: 9%
                }
            </style>
            <div id="rcsWpLibShortUrlTable">
                <?php print $this->renderMappingTable(); ?>
            </div>
            <br>
            <input type="text" id="rcsWpLibShortUrlCode" maxlength="32"></input>
            <input type="text" id="rcsWpLibShortUrlInput" maxlength="255"></input>
            <input type="button" id="rcsWpLibShortUrlAddBtn" value="Add"
                data-url="<?php echo admin_url('admin-ajax.php'); ?>"
                data-nonce="<?php echo wp_create_nonce($this->tabId); ?>"
                data-action="<?php echo self::AJAX_ADD_SHORT_URL; ?>"></input>
            <br>
            <em>The Short URL can be left blank to have a code generated.</em>
        <?php
            },
            $pageSlug
        );
    }

    private function renderMappingTable(): string
    {
        $urlMappings = $this->handler->getFrontEndUrls();
        $ajaxUrl = admin_url('admin-ajax.php');
        $ajaxNonce = wp_create_nonce($this->tabId);

        ob_start();
        ?>
        <table>
            <caption>List of Short URL to Long URL mappings</caption>
            <thead>
                <tr>
                    <th>Short URL</th>
                    <th>Long URL</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urlMappings as $urlMapping) { ?>
                    <tr>
                        <td><?php echo $urlMapping->shortUrl; ?></td>
                        <td><?php echo $urlMapping->longUrl; ?></td>
                        <td>
                            <span
                                class="rcsWpLibShortUrlDeleteBtn"
                                data-rcs-wp-lib-shorturlcode="<?php echo $urlMapping->shortCode; ?>"
                                data-url="<?php echo $ajaxUrl; ?>"
                                data-nonce="<?php echo $ajaxNonce; ?>"
                                data-action="<?php echo self::AJAX_DELETE_SHORT_URL; ?>">

                                <svg
                                    aria-hidden="true" focusable="false" data-prefix="fas"
                                    data-icon="trash-alt" class="svg-inline--fa fa-trash-alt fa-w-14"
                                    role="img" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 448 512">
                                    <path fill="currentColor"
                                        d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z">
                                    </path>
                                </svg>
                            </span>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
<?php
        $html = MinifyHelper::minifyHtml(ob_get_contents());
        ob_end_clean();

        return $html;
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param string $pageSlug
     *            The page slug for any errors.
     * @param array<string, string> $input
     *            Contains all settings fields as array keys
     *
     * @return array<string, string>
     */
    public function sanitize(string $pageSlug, ?array $input): ?array
    {
        return $input;
    }

    public function onEnqueueScripts(string $pluginName, string $pluginBaseUrl, string $pluginVersion): void
    {
        wp_enqueue_script(
            $this->tabId,
            $pluginBaseUrl . 'vendor/raincity/rcs-wp-library/src/js/rcs-wp-lib-shorturl.js',
            array('jquery'),
            $pluginVersion,
            true
        );

        //         wp_localize_script(
        //             $this->tabId,
        //             'raincity_wpf_shorturl_ajax_obj',
        //             array(
        //                 'ajax_url' => admin_url('admin-ajax.php'),
        //                 'nonce' => wp_create_nonce($this->tabId),
        //                 'addUrlAction' => self::AJAX_ADD_SHORT_URL,
        //                 'deleteUrlAction' => self::AJAX_DELETE_SHORT_URL
        //                 )
        //             );
    }


    public function ajaxAddUrl(): void
    {
        $this->logger->debug('Ajax request to add new Short URL');

        if (
            1 === check_ajax_referer($this->tabId) &&
            current_user_can('administrator') &&
            isset($_REQUEST['new_url'])
        ) {
            $shortCode = $_REQUEST['short_code'] ?? '';

            $resp = new \stdClass();
            $resp->code = 200;
            $url = $_REQUEST['new_url'];

            try {
                if (strlen($shortCode) > 0) {
                    $this->handler->addShortUrl($shortCode, $url, true);
                } else {
                    $this->handler->createShortUrl($url, true);
                }

                $resp->table = $this->renderMappingTable();
            } catch (\InvalidArgumentException $iae) {
                $resp->code = $iae->getCode();
                $resp->error = $iae->getMessage();
            }

            echo json_encode($resp);
        }

        wp_die(); // All ajax handlers die when finished
    }

    public function ajaxDeleteUrl(): void
    {
        $result = '';
        $this->logger->debug('Ajax request to delete Short URL');

        if (1 === check_ajax_referer($this->tabId) && current_user_can('administrator')) {
            if (isset($_REQUEST['shortcode'])) {
                $this->handler->deleteShortCode($_REQUEST['shortcode']);

                $resp = new \stdClass();
                $resp->code = 200;
                $resp->table = $this->renderMappingTable();

                echo json_encode($resp);
            } else {
                $result = new \WP_Error(404, 'No URL short code provided.');
            }
        }

        wp_die($result); // All ajax handlers die when finished
    }
}
