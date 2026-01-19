<?php
declare(strict_types=1);
namespace Fixtures;

use RCS\WP\Settings\AdminSettingsTab;
use RCS\WP\Settings\FormFieldInfo;

class TestAdminSettingsTab
    extends AdminSettingsTab
{
    /** @var array<string,array<mixed>> */
    public array $called = [];

    public function addSettings(string $pageSlug): void
    {
        $this->called['addSettings'][] = $pageSlug;
    }

    public function sanitize(string $pageSlug, ?array $input): ?array
    {
        $this->called['sanitize'][] = [$pageSlug, $input];

        return $input;
    }

    public function exposeInit(string $pageSlug): void
    {
        $this->called['initSettings'][] = [$pageSlug];

        $this->initSettings($pageSlug);
    }

    public function getIdPublic(): string { return $this->getId(); }
    public function getNamePublic(): string { return $this->getName(); }
    public function renderTextPublic(string $key, string $desc = ''): string
    {
        ob_start();
        $this->renderTextField($key, $desc);
        return ob_get_clean();
    }

    public function callGetFormFieldInfo(string $key): ?FormFieldInfo
    {
        return $this->getFormFieldInfo($key);
    }

    public function onEnqueueScripts(string $pluginName, string $pluginBaseUrl, string $pluginVersion): void
    {
        $this->called['onEnqueueScripts'][] = [$pluginName, $pluginBaseUrl, $pluginVersion];
    }

    public function registerActions(): void
    {
        $this->called['registerActions'][] = ['void'];
    }

    // Expose protected validators
    public function validateString(
        string $key,
        string $value,
        string $pageSlug,
        string $fieldName,
        string $errMsg = 'Missing value'
        ): void {
            $this->validateStringValue($key, $value, $pageSlug, $fieldName, $errMsg);
    }

    public function validateEmail(
        string $key,
        string $value,
        string $pageSlug,
        string $fieldName,
        string $errMsg = 'Invalid Email address'
        ): void {
            $this->validateEmailAddress($key, $value, $pageSlug, $fieldName, $errMsg);
    }

    public function validateNumber(
        string $key,
        string $value,
        string $pageSlug,
        string $fieldName,
        ?int $min = null,
        ?int $max = null,
        ?string $errMsg = null
        ): void {
            $this->validateNumericValue($key, $value, $pageSlug, $fieldName, $min, $max, $errMsg);
    }
}
