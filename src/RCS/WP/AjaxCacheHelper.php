<?php

declare(strict_types=1);

namespace RCS\WP;

use Override;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RCS\WP\Ajax\AjaxHandlerImplInf;
use RCS\WP\Ajax\AjaxHandlerImplTrait;
use RCS\WP\Ajax\AjaxHandlerResponse;

/**
 * Helper class for clearing a data cache
 *
 */
class AjaxCacheHelper implements AjaxHandlerImplInf
{
    use AjaxHandlerImplTrait;

    const AJAX_ACTION_CLEAR_DATA_CACHE = 'wpfDataCacheClear';

    public function __construct(
        private readonly PluginInfoInterface $pluginInfo,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
        add_action('admin_print_footer_scripts', [$this, 'injectJavaScript'], 100);
    }

    #[Override]
    public static function getAjaxActions(): array
    {
        return [
            self::AJAX_ACTION_CLEAR_DATA_CACHE
        ];
    }

    public function handlePrivateAjaxRequest(string $action): AjaxHandlerResponse
    {
        $this->ajaxClearDataCache();

        return new AjaxHandlerResponse();
    }

    public function onAdminEnqueueScripts(): void
    {
        wp_localize_script(
            $this->pluginInfo->getName(),
            'raincity_dataCacheObj',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce($this->pluginInfo->getName()),
                'plugin'   => $this->pluginInfo->getName()
            )
        );
    }

    public function injectButton(string $label = 'Clear Data Cache'): void
    {
        printf('<input type="button" class="wpfclearcache button button-secondary" value="%s" name="clearCache">', $label);
    }

    public function injectJavaScript(): void
    {
?>
        <script type='text/javascript'>
            jQuery(document).ready(function($) {
                $(".wpfclearcache").click(function() {
                    $.post(
                        "<?php echo admin_url('admin-ajax.php'); ?>", {
                            _ajax_nonce: "<?php echo wp_create_nonce($this->pluginInfo->getName()); ?>",
                            action: "<?php echo self::AJAX_ACTION_CLEAR_DATA_CACHE; ?>",
                            plugin: "<?php echo $this->pluginInfo->getName(); ?>"
                        },
                        function(data) {}
                    );
                });
            });
        </script>
<?php
    }

    public function ajaxClearDataCache(): void
    {
        $this->logger->debug('Ajax request to run Clear DataCache');

        check_ajax_referer($this->pluginInfo->getName());

        $ajaxPluginName = $_REQUEST['plugin'];
        if ($ajaxPluginName == $this->pluginInfo->getName()) {

            $this->logger->debug('Clearing cache');
            // Handle the ajax request
            $this->cache->clear();
        }
        wp_die(); // All ajax handlers die when finished
    }
}
