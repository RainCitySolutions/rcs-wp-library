<?php
declare (strict_types=1);
namespace RCS\PDF;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RCS\TestHelper\RainCityTestCase;
use RCS\Util\ReflectionHelper;

#[CoversClass(\RCS\PDF\PDFService::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class PDFServiceTest extends RainCityTestCase
{
    private const GOOD_TEST_URL = 'https://good.url.co';
    private const PDF_LOCATION_URL = 'http://test.location.co/test.pdf';

    private function getTestInstance(): PDFService
    {
        $testUrl = self::GOOD_TEST_URL;
        $responses = array(
            new Response(200, array('Allow' => PDFService::EXPECTED_VERBS))
        );

        $mockClient = $this->initializeMockHttpClient($responses);

        return new PDFService($testUrl, self::createMock(LoggerInterface::class), $mockClient);
    }

    /**
     * Tests PDFService->__construct()
     */
    public function testCtor_goodUrlWithClient(): void
    {
        $testUrl = self::GOOD_TEST_URL;
        $responses = array(
            new Response(200, array('Allow' => PDFService::EXPECTED_VERBS))
        );

        $mockClient = $this->initializeMockHttpClient($responses);

        $pdfSvc = new PDFService($testUrl, self::createMock(LoggerInterface::class), $mockClient);

        $logger = ReflectionHelper::getObjectProperty(PDFService::class, 'logger', $pdfSvc);
        self::assertNotNull($logger);

        $client = ReflectionHelper::getObjectProperty(PDFService::class, 'httpClient', $pdfSvc);
        self::assertSame($mockClient, $client);

        $url = ReflectionHelper::getObjectProperty(PDFService::class, 'svcUrl', $pdfSvc);
        self::assertSame($testUrl, $url);
    }

    /**
     * Tests PDFService->__construct()
     */
    public function testCtor_badUrl(): void
    {
        $this->expectException(PDFServiceException::class);
        $this->expectExceptionMessage(PDFService::INVALID_URL_ERROR);

        new PDFService('not A url', self::createMock(LoggerInterface::class));    // NOSONAR - ignore useless instantiation
    }

    /**
     * Tests PDFService->__construct()
     */
    public function testSetHttpClient(): void
    {
        $pdfSvc = $this->getTestInstance();

        $testClient = $this->initializeMockHttpClient();

        $pdfSvc->setHttpClient($testClient);

        $client = ReflectionHelper::getObjectProperty(PDFService::class, 'httpClient', $pdfSvc);

        self::assertSame($testClient, $client);
    }

    /**
     * Tests PDFService->isValidServiceUrl()
     */
    public function testIsValidServiceUrl_invalidUrl(): void
    {
        $pdfSvc = $this->getTestInstance();

        $result = ReflectionHelper::invokeObjectMethod(PDFService::class, $pdfSvc, 'isValidServiceUrl', 'not a url');
        self::assertFalse($result);
    }

    /**
     * Tests PDFService->isValidServiceUrl()
     */
    public function testIsValidServiceUrl_200NoHeader(): void
    {
        $pdfSvc = $this->getTestInstance();

        $responses = array(
            new Response()  // will return 200 but no Allow header
        );
        $pdfSvc->setHttpClient($this->initializeMockHttpClient($responses));

        $result = ReflectionHelper::invokeObjectMethod(
            PDFService::class,
            $pdfSvc,
            'isValidServiceUrl',
            self::GOOD_TEST_URL
            );
        self::assertFalse($result);
    }

    /**
     * Tests PDFService->isValidServiceUrl()
     */
    public function testIsValidServiceUrl_400(): void
    {
        $pdfSvc = $this->getTestInstance();

        $responses = array(
            new Response(400)  // will return 400
        );
        $pdfSvc->setHttpClient($this->initializeMockHttpClient($responses));

        $result = ReflectionHelper::invokeObjectMethod(
            PDFService::class,
            $pdfSvc,
            'isValidServiceUrl',
            self::GOOD_TEST_URL
            );
        self::assertFalse($result);
    }

    /**
     * Tests PDFService->isValidServiceUrl()
     */
    public function testIsValidServiceUrl_200WrongVerbs(): void
    {
        $pdfSvc = $this->getTestInstance();

        $responses = array(
            new Response(200, array('Allow' => 'GET,POST'))  // will return 200 with Allow header
        );
        $pdfSvc->setHttpClient($this->initializeMockHttpClient($responses));

        $result = ReflectionHelper::invokeObjectMethod(
            PDFService::class,
            $pdfSvc,
            'isValidServiceUrl',
            self::GOOD_TEST_URL
            );
        self::assertFalse($result);
    }

    /**
     * Tests PDFService->isValidServiceUrl()
     */
    public function testIsValidServiceUrl_exception(): void
    {
        $pdfSvc = $this->getTestInstance();

        $responses = array(
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        );
        $pdfSvc->setHttpClient($this->initializeMockHttpClient($responses));

        $result = ReflectionHelper::invokeObjectMethod(
            PDFService::class,
            $pdfSvc,
            'isValidServiceUrl',
            self::GOOD_TEST_URL
            );
        self::assertFalse($result);
    }

    /**
     * Tests PDFService->isValidServiceUrl()
     */
    public function testIsValidServiceUrl_allGood(): void
    {
        $pdfSvc = $this->getTestInstance();

        $responses = array(
            new Response(200, array('Allow' => PDFService::EXPECTED_VERBS))  // will return 200 with Allow header
        );
        $pdfSvc->setHttpClient($this->initializeMockHttpClient($responses));

        $result = ReflectionHelper::invokeObjectMethod(
            PDFService::class,
            $pdfSvc,
            'isValidServiceUrl',
            self::GOOD_TEST_URL
            );
        self::assertTrue($result);
    }

    /**
     * Tests PDFService->isServiceActive()
     */
    public function testIsServiceActive_yes(): void
    {
        $responses = array(
            new Response(200, array('Allow' => PDFService::EXPECTED_VERBS)),    // will be used by ctor
            new Response(200, array('Allow' => PDFService::EXPECTED_VERBS))     // will be used for isServiceActive
        );

        $mockClient = $this->initializeMockHttpClient($responses);

        $pdfSvc =  new PDFService(self::GOOD_TEST_URL, self::createMock(LoggerInterface::class), $mockClient);

        $result = $pdfSvc->isServiceActive();

        self::assertTrue($result);
    }

    /**
     * Tests PDFService->isServiceActive()
     */
    public function testIsServiceActive_no(): void
    {
        $responses = array(
            new Response(200, array('Allow' => PDFService::EXPECTED_VERBS)),    // will be used by ctor
            new Response(400)     // will be used for isServiceActive
        );

        $mockClient = $this->initializeMockHttpClient($responses);

        $pdfSvc =  new PDFService(self::GOOD_TEST_URL, self::createMock(LoggerInterface::class), $mockClient);

        $result = $pdfSvc->isServiceActive();

        self::assertFalse($result);
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_validateRequest(): void
    {
        $testXmlFile = $this->getTempFile();
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();
        $testSrcPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->mockHttpResponses->append(
            new Response(201, array('Location' => self::PDF_LOCATION_URL)),
            new Response(200, array(), file_get_contents($testSrcPdfFile))
        );

        $pdfSvc->fetchPDF($testXmlFile, $testXsltFile, $testPdfFile, array());

        self::assertCount(2, $this->httpHistory);

        $this->validateGenerateRequest(array_shift($this->httpHistory)['request']);
        $this->validateFetchRequest(array_shift($this->httpHistory)['request']);
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_validateWithSupportFiles(): void
    {
        $testXmlFile = $this->getTempFile();
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();
        $testSupportFile = $this->getTempFile();
        $testSrcPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->mockHttpResponses->append(
            new Response(201, array('Location' => self::PDF_LOCATION_URL)),
            new Response(200, array(), file_get_contents($testSrcPdfFile))
            );

        $pdfSvc->fetchPDF($testXmlFile, $testXsltFile, $testPdfFile, array($testSupportFile));

        self::assertCount(2, $this->httpHistory);

        $this->validateGenerateRequest(array_shift($this->httpHistory)['request']);
        $this->validateFetchRequest(array_shift($this->httpHistory)['request']);
    }

    private function validateGenerateRequest(RequestInterface $request): void
    {
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals(self::GOOD_TEST_URL, $request->getUri()->__toString());

        $contentType = $request->getHeader('Content-Type');
        self::assertCount(1, $contentType);
        self::assertStringStartsWith('multipart/form-data; ', array_shift($contentType));

//         $body = $request->getBody()->getContents();
        // TODO: parse body to ensure everything was included

//         self::assertNotNull($body);
    }

    private function validateFetchRequest(RequestInterface $request): void
    {
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals(self::PDF_LOCATION_URL, $request->getUri()->__toString());
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_missingSupportFile(): void
    {
        $testXmlFile = $this->getTempFile();
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->mockHttpResponses->append(
            new Response(400, array(), 'File not found: Common.xslt')
            );

        $this->expectException(PDFServiceException::class);

        $pdfSvc->fetchPDF($testXmlFile, $testXsltFile, $testPdfFile, array());
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_pdfFileError(): void
    {
        $testXmlFile = $this->getTempFile();
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->mockHttpResponses->append(
            new Response(201, array('Location' => self::PDF_LOCATION_URL)),
            new Response(404)
            );

        $this->expectException(PDFServiceException::class);

        $pdfSvc->fetchPDF($testXmlFile, $testXsltFile, $testPdfFile, array());
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_missingXmlFileError(): void
    {
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->expectException(PDFServiceException::class);

        $pdfSvc->fetchPDF('noSuchFile.xml', $testXsltFile, $testPdfFile, array());
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_missingXsltFileError(): void
    {
        $testXmlFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->expectException(PDFServiceException::class);

        $pdfSvc->fetchPDF($testXmlFile, 'noSuchFile.xslt', $testPdfFile, array());
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_missingSupportFiles(): void
    {
        $testXmlFile = $this->getTempFile();
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->expectException(PDFServiceException::class);

        $pdfSvc->fetchPDF($testXmlFile, $testXsltFile, $testPdfFile, array('noSuchFile.png'));
    }

    /**
     * Tests PDFService->fetchPDF()
     */
    public function testFetchPDF_serverError(): void
    {
        $testXmlFile = $this->getTempFile();
        $testXsltFile = $this->getTempFile();
        $testPdfFile = $this->getTempFile();

        $pdfSvc = $this->getTestInstance();
        $this->resetHttpHistory();

        $this->mockHttpResponses->append(
            new RequestException('Internal Server Error', new Request('POST', self::GOOD_TEST_URL))
            );

        $this->expectException(PDFServiceException::class);

        $pdfSvc->fetchPDF($testXmlFile, $testXsltFile, $testPdfFile, array());
    }
}
