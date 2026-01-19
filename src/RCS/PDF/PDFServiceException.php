<?php
declare(strict_types=1);
namespace RCS\PDF;

/**
 */
class PDFServiceException
    extends \Exception
{
    const INVALID_URL_ERROR = -1;
    const GENERATION_ERROR = -2;
    const PDF_FETCH_ERROR = -4;
    const PDF_FILE_ERROR = -8;
}
