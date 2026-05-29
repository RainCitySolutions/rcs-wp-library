<?php
declare(strict_types = 1);
namespace RCS\WP\Ajax;


/**
 * This trait provides a default implemementation for the AjaxHandlerImplInf
 * interface. Its primary use would be to avoid having to implement both
 * handlePublicAjaxRequest() and handlePrivateAjaxRequest() if the handler
 * only uses one of the functions.
 */
trait AjaxHandlerImplTrait
{
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
    public function handlePublicAjaxRequest(string $action): AjaxHandlerResponse
    {
        return new AjaxHandlerResponse('0', 400);
    }

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
    public function handlePrivateAjaxRequest(string $action): AjaxHandlerResponse
    {
        return new AjaxHandlerResponse('0', 400);
    }
}
