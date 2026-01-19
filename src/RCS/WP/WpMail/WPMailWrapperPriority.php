<?php
declare(strict_types = 1);
namespace RCS\WP\WpMail;

/**
 * Priorities supported by WPMailWrapper
 */
enum WPMailWrapperPriority: int
{
    case HIGH = 1;
    case NORMAL = 3;
    case LOW = 5;
}
