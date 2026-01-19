<?php
declare(strict_types = 1);
namespace Fixtures;

use RCS\WP\Settings\AdminSettings;


final class TestAdminSettings extends AdminSettings
{
    public function exposeInitialize(): void
    {
        $this->initializeInstance();
    }
}
