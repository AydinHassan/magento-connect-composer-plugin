<?php

namespace AydinHassan\MagentoConnectPlugin;

use Composer\Downloader\FilesystemException;
use Composer\Downloader\TarDownloader as ComposerTarDownloader;

/**
 * CLI Downloader for tar files: tar, tar.gz or tar.bz2
 * (tar.bz2 was not checked)
 *
 * @author Andrew Roslik <bizkirby@gmail.com>
 */
class TarDownloader extends ComposerTarDownloader
{
    /**
     * Archive code
     */
    const ARCHIVE_CODE = 'tar';

    /**
     * {@inheritDoc}
     */
    protected function extract($file, $path)
    {
        try {
            /**
             * Extract file via PharData
             *
             * @see Composer\Downloader\TarDownloader::extract()
             */
            parent::extract($file, $path);
        } catch (\UnexpectedValueException $e) {
            $output = $this->extractViaCli($file, $path);
            if (null !== $output) {
                throw new FilesystemException(
                    'There are errors on unpacking the archive by tar CLI command:' . PHP_EOL . $output,
                    null, $e
                );
            }
        }
    }

    /**
     * Extract file by "tar" CLI command
     *
     * @param string $file
     * @param string $path
     * @return mixed
     */
    protected function extractByTarCommand($file, $path)
    {
        if ('gz' == pathinfo($file, PATHINFO_EXTENSION)) {
            return `tar -xfz $file -C $path 2>&1`;
        } else {
            return `tar -xf $file -C $path 2>&1`;
        }
    }

    /**
     * Check command run under Bash or another UNIX shell
     *
     * Here might be an error if user registered the same alias for Windows CLI
     *
     * @return bool
     */
    protected function isBash()
    {
        return false === strpos(`ls 2>&1`, "'ls'") || false === strpos(`sh --version 2>&1`, "'sh'");
    }

    /**
     * Convert bash
     *
     * @param string $path
     * @return string
     */
    private function convertPathToWindowsBashFormat($path)
    {
        //convert Windows directory separator
        $path = str_replace('\\', '/', $path);

        //convert path for GitBash on Windows
        $path = preg_replace('/^([A-z])[:]/', '/$1', $path);
        return $path;
    }

    /**
     * Create target directory if it does not exist
     *
     * @param string $path
     * @param int $permissions
     * @return bool
     */
    private function mkdir($path, $permissions = 0777)
    {
        if (!is_dir($path)) {
            return mkdir($path, $permissions, true);
        }
        return true;
    }

    /**
     * Check Windows system
     *
     * @return bool
     */
    private function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * @param string $file
     * @param string $path
     * @return string
     */
    private function extractViaCli($file, $path)
    {
        if ($this->isWindows() && $this->isBash()) {
            $path = $this->convertPathToWindowsBashFormat($path);
            $file = $this->convertPathToWindowsBashFormat($file);
        }
        $this->mkdir($path);
        return $this->extractByTarCommand($file, $path);
    }
}
