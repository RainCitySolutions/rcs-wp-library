<?php
declare(strict_types=1);
namespace RCS\Util;

class FileSystem
{
    /**
     * Creates a temporary file in the system temp directory with the
     * specified extension.
     *
     * @param string $extension The desired file extention.
     *
     * @return string The full path to the tempoary file. May return FALSE if
     *      the temporary file cannot be created.
     */
    public static function createTempFile(string $extension): string|false
    {
        $finalFilename = false;
        $tmpFilename = tempnam(sys_get_temp_dir(), '');

        if ($tmpFilename) {
            $finalFilename = $tmpFilename . $extension;

            if (!rename ($tmpFilename, $finalFilename)) {
                $finalFilename = false;
            }
        }

        return $finalFilename;
    }

    /**
     * Purge the contents of a folder including all files and folders.
     *
     * @param string $path
     *
     * @throws \UnexpectedValueException If the directory does not exist.
     * @throws \ValueError If the directory is an empty string.
     */
    public static function purgeFolder(string $path): void
    {
        $iterator = new \DirectoryIterator($path);

        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isDot()) {
                if ($fileinfo->isDir()) {
                    self::purgeFolder($fileinfo->getPathname());
                    @rmdir($fileinfo->getPathname());
                } elseif ($fileinfo->isFile()) {
                    @unlink($fileinfo->getPathname());
                }
            }
        }
    }
}
