<?php
declare (strict_types=1);
namespace RCS\PDF;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class to support sending requests to the PDF Service.
 */
class PDFService
{
    const EXPECTED_VERBS = 'POST,OPTIONS';
    const INVALID_URL_ERROR = 'Invalid service URL provided.';

    protected string $svcUrl;

    private ClientInterface $httpClient;

    /**
     * Constructs a PDF Serivce instance.
     *
     * If the $httpClient parameter is not set the Guzzle HTTP Client will be
     * used by default. See {@link https://docs.guzzlephp.org/en/stable/}

     *
     * @param string $svcUrl The URL to the PDF Web Service
     * @param ClientInterface $httpClient (Optional) An HTTP Client to be used for
     *      requests. Can be overridden later by a call to {@see PDFService::setHttpClient()}.
     * @param float $timeout Timeout for the default HTTP client. Ignored if
     *      $httpClient is provided.
     *
     * @throws PDFServiceException Thrown if the URL provided does not refer
     *      to a valid PDF Service instance.
     */
    public function __construct(
        string $svcUrl,
        private LoggerInterface $logger,
        ClientInterface $httpClient = null,
        float $timeout = 30.0
        )
    {
        $this->logger = $logger;

        $this->httpClient = $httpClient ?? self::getDefaultHttpClient($timeout);

        if ($this->isValidServiceUrl($svcUrl)) {
            $this->svcUrl = $svcUrl;
        } else {
            throw new PDFServiceException(self::INVALID_URL_ERROR, PDFServiceException::INVALID_URL_ERROR);
        }
    }

    /**
     * Set the HTTP Client to use for requests to the PDF Service.
     *
     * If not called, the Guzzle HTTP Client will be used by default.
     * @link https://docs.guzzlephp.org/en/stable/
     *
     * @param ClientInterface $httpClient An HTTP Client.
     */
    public function setHttpClient(ClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    protected static function getDefaultHttpClient(float $timeout = 30.0): ClientInterface
    {
        return new Client([
            // You can set any number of default request options.
            'timeout'  => $timeout,
            'read_timeout' => $timeout,
            'cookies' => true,
            'verify' => true,
            'http_errors' => false
        ]);
    }

    /**
     * Check if the provided URL refers to an instance of the PDF service.
     *
     * @param string $url The URL for the PDF service.
     *
     * @return bool Returns true if the URL is valid, otherwise returns false.
     */
    protected function isValidServiceUrl(string $url): bool
    {
        $isValid = false;

        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            // get the initial login page (just contains the email/username field
            try {
                /** @var ResponseInterface $response */
                $response = $this->httpClient->sendRequest(new Request('OPTIONS', $url));

                if (200 === $response->getStatusCode()) {
                    $verbs = $response->getHeader('Allow');

                    if (!empty($verbs) && self::EXPECTED_VERBS === $verbs[0]) {
                        $isValid = true;
                    }
                    else {
                        $this->logger->warning(
                            'Necessary HTTP verbs not supported. Response was {verbs}',
                            array('verbs' => $verbs)
                            );
                    }
                }
                else {
                    $this->logger->warning(
                        'OPTIONS request returned {errorCode}: {reason}',
                        array(
                            'errorCode' => $response->getStatusCode(),
                            'reason' => $response->getReasonPhrase()
                        )
                        );
                }
            } catch (\Exception $e) {
                $this->logger->warning(
                    'Unable to retrieve Options from URL {url}, {error}',
                    array('url' => $url, 'error' => $e->getMessage())
                    );
            }
        }

        return $isValid;
    }

    /**
     * Check if the PDF Service is active
     *
     * @return bool Returns true if the serivce is active, otherwise returns
     *      false.
     */
    public function isServiceActive(): bool
    {
        return $this->isValidServiceUrl($this->svcUrl);
    }


    private const FILES_FIELD = 'files[]';

    /**
     * Sends the XML to the PDF Service and retrieves the PDF file.
     *
     * @param string $xmlFilename   The fully qualified name of the XML file.
     * @param string $xsltFilename The fully qualified name of the XSLT file.
     * @param string $pdfFilename   The fully qualified name of the PDF file.
     * @param array<string> $supportFiles An array of fully qualified filenames to be
     *      included with the request to the PDF service. These might include
     *      background images, or child XSLT files referenced by the XSLT file.
     *
     * @return bool  Returns true if the PDF file was retrieved, otherwise false.
     *
     * @throws PDFServiceException
     */
    public function fetchPDF(string $xmlFilename, string $xsltFilename, string $pdfFilename, array $supportFiles): bool
    {
        $result = false;

        $multipartData = array();

        // set the names of the input and output files
        array_push($multipartData, array('name' => 'xmlfile', 'contents' => basename($xmlFilename)));
        array_push($multipartData, array('name' => 'xsltfile', 'contents' => basename($xsltFilename)));
        array_push($multipartData, array('name' => 'pdffile', 'contents' => basename($pdfFilename)));

        try {
            // Add the support files
            foreach ($supportFiles as $file) {
                array_push($multipartData, array(
                    'name' => self::FILES_FIELD,
                    'filename' => $file,
                    'contents' => Utils::tryFopen($file, 'r')
                    ));
            }

            // add the xml file
            array_push($multipartData, array(
                'name' => self::FILES_FIELD,
                'filename' => $xmlFilename,
                'contents' => Utils::tryFopen($xmlFilename, 'r')
                ));

            // add the XSLT file
            array_push($multipartData, array(
                'name' => self::FILES_FIELD,
                'filename' => $xsltFilename,
                'contents' => Utils::tryFopen($xsltFilename, 'r')
                ));
        } catch (\RuntimeException $re) {
            throw new PDFServiceException(
                'Unable to read file(s) needed for PDF generation: ' . $re->getMessage(),
                PDFServiceException::PDF_FILE_ERROR
                );
        }

        try {
            // send the conversion request to the service as Multipart form data
            $multipartStrm = new MultipartStream($multipartData);
            $request = new Request('POST', $this->svcUrl, array(), $multipartStrm);
            $response = $this->httpClient->sendRequest($request);

            // If the request was successful, extract the URL of the PDF file and fetch it
            if (201 === $response->getStatusCode()) {
                $location = $response->getHeader('Location');

                if (!empty($location)) {
                    $this->fetchPdfFile(array_shift($location), $pdfFilename);
                    $result = true;
                }
            } else {
                $reason = $response->getBody()->getContents();

                if (empty($reason)) {
                    $reason = $response->getReasonPhrase();
                }

                throw new PDFServiceException(
                    'Error requesting PDF: ' . $reason,
                    PDFServiceException::GENERATION_ERROR
                    );
            }
        } catch (ClientExceptionInterface $cei) {
            throw new PDFServiceException(
                'Error generating PDF: ' . $cei->getMessage(),
                PDFServiceException::GENERATION_ERROR
                );
        }

        return $result;
    }

    /**
     * Fetch the PDF file from the URL.
     *
     * @param string $pdfUrl    The URL to the PDF file.
     * @param string $pdfFilename The file to write the PDF contents to.
     *
     * @throws PDFServiceException Thrown if there is an issue fetching the
     *      URL or writting the PDF to the file.
     */
    private function fetchPdfFile(string $pdfUrl, string $pdfFilename): void
    {
        // fetch the PDF file and write it to the specified file
        $request = new Request('GET', $pdfUrl);
        $response = $this->httpClient->sendRequest($request);

        if (200 === $response->getStatusCode()) {
            try {
                $outRsrc = Utils::tryFopen($pdfFilename, 'w');
                $outStrm = Utils::streamFor($outRsrc);
                Utils::copyToStream($response->getBody(), $outStrm);
                fclose($outRsrc);
            } catch (\RuntimeException $re) {
                throw new PDFServiceException(
                    'Unable to create PDF file: ' . $re->getMessage(),
                    PDFServiceException::PDF_FILE_ERROR
                    );
            }
        } else {
            $reason = $response->getBody()->getContents();

            if (empty($reason)) {
                $reason = $response->getReasonPhrase();
            }

            throw new PDFServiceException(
                'Error retrieving PDF: ' . $reason,
                PDFServiceException::PDF_FETCH_ERROR
                );
        }
    }
}
