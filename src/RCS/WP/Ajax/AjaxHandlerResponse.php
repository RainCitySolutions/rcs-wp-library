<?php
declare(strict_types = 1);
namespace RCS\WP\Ajax;

/**
 * Represents the result of an AJAX handler.
 *
 */
class AjaxHandlerResponse
{
    /**
     *
     * @param string|\WP_Error $message If this is a WP_Error object, and not
     *      an Ajax or XML-RPC request, the error’s messages are used.
     * @param string|int $title If $message is a WP_Error object, error data
     *      with the key 'title' may be used to specify the title. If $title
     *      is an integer, then it is treated as the response code.
     */
    public function __construct(
        private string|\WP_Error $message = '',
        private string|int $title = '')
    {
    }

    /**
     * Fetch the message.
     *
     * @return string|\WP_Error
     */
    public function getMessage(): string|\WP_Error
    {
        return $this->message;
    }

    /**
     * Fetch the title.
     *
     * @return string|int
     */
    public function getTitle(): string|int
    {
        return $this->title;
    }
}
