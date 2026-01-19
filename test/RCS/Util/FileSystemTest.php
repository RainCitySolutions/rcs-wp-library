<?php
declare(strict_types=1);
namespace RCS\Util;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use org\bovigo\vfs\vfsStream;

#[CoversClass(FileSystem::class)]
class FileSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    #[Test]
    public function createTempFile_returns_path_on_success(): void
    {
        $extension = '.txt';
        $baseTemp = '/tmp/php_raw';

        // Mocking native PHP functions
        \Brain\Monkey\Functions\expect('sys_get_temp_dir')->once()->andReturn('/tmp');
        \Brain\Monkey\Functions\expect('tempnam')->once()->with('/tmp', '')->andReturn($baseTemp);
        \Brain\Monkey\Functions\expect('rename')->once()->with($baseTemp, $baseTemp . $extension)->andReturn(true);

        $result = FileSystem::createTempFile($extension);

        $this->assertSame($baseTemp . $extension, $result);
    }

    #[Test]
    public function createTempFile_returns_false_if_rename_fails(): void
    {
        \Brain\Monkey\Functions\expect('sys_get_temp_dir')->andReturn('/tmp');
        \Brain\Monkey\Functions\expect('tempnam')->andReturn('/tmp/file');
        // Simulate rename failure
        \Brain\Monkey\Functions\expect('rename')->andReturn(false);

        $result = FileSystem::createTempFile('.log');

        $this->assertFalse($result);
    }

    #[Test]
    public function purgeFolder_throws_exception_if_path_empty(): void
    {
        $this->expectException(\ValueError::class);
        FileSystem::purgeFolder('');
    }

    /**
     * Note: Testing Recursive Directory Iterators with BrainMonkey is limited
     * because 'new DirectoryIterator' cannot be mocked via BrainMonkey.
     * For full coverage of purgeFolder, using vfsStream is recommended.
     */

    #[Test]
    public function testPurgeFolder_RemovesFilesAndSubfolders(): void
    {
        // define my virtual file system
        $directory = [
            'phpunit_fs_test' => [
                'sub' => [
                    'file2.txt' => 'data2'
                ],
                'file1.txt' => 'data1'
            ]
        ];
        // setup and cache the virtual file system
        $fs = vfsStream::setup('root', 444, $directory);

        self::assertFileExists($fs->url() . '/phpunit_fs_test/file1.txt');
        self::assertFileExists($fs->url() . '/phpunit_fs_test/sub/file2.txt');

        FileSystem::purgeFolder($fs->url() . '/phpunit_fs_test');

        self::assertFileDoesNotExist($fs->url() . '/phpunit_fs_test/file1.txt', 'Root file should be deleted');
        self::assertFileDoesNotExist($fs->url() . '/phpunit_fs_test/sub/file2.txt', 'Nested file should be deleted');
        self::assertDirectoryDoesNotExist($fs->url() . '/phpunit_fs_test/sub', 'Subdirectory should be deleted');
    }

    #[Test]
    public function testPurgeFolder_ThrowsUnexpectedValueExceptionForInvalidDirectory(): void
    {
        // setup and cache the virtual file system
        $fs = vfsStream::setup('root', 444, []);

        self::expectException(\UnexpectedValueException::class);
        new \DirectoryIterator($fs->url() . '/invalid_path_for_test_12345'); // NOSONAR - not useless
    }
}
