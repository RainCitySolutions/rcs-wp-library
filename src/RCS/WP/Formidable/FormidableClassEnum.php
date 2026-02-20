<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

/**
 * Enumeration of Formidble classes.
 *
 * Used by {@link \RCS\WP\Formidable\Formidable} for caching key/id pairs for
 * various Formidable classes.
 */
enum FormidableClassEnum: string
{
    case Field = 'FrmField';
    case Form = 'FrmForm';
    case View = 'FrmViewsDisplay';
}
