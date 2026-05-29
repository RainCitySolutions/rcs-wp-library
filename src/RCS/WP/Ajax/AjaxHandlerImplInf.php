<?php
declare(strict_types = 1);
namespace RCS\WP\Ajax;


interface AjaxHandlerImplInf
{
    /**
     * Fetch the actions implemented by the handler.
     *
     * @return string[]
     */
    public static function getAjaxActions(): array;

    /**
     * Handled a public AJAX request.
     *
     * Implementations should not call wp_die() at the end. This is handled
     * internally.
     * <p>
     * Handlers can use the $action parameter to support handling multiple
     * actions through a single handler.
     *
     * @param string $action The action being invoked.
     *
     * @return AjaxHandlerResponse
     */
    public function handlePublicAjaxRequest(string $action): AjaxHandlerResponse;

    /**
     * Handled a private AJAX request.
     *
     * Implementations should not call wp_die() at the end. This is handled
     * internally.
     * <p>
     * Handlers can use the $action parameter to support handling multiple
     * actions through a single handler.
     *
     * @param string $action The action being invoked.
     *
     * @return AjaxHandlerResponse
     */
    public function handlePrivateAjaxRequest(string $action): AjaxHandlerResponse;
}
