<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category   Tools
 * @package    TechDivision_Markman
 * @subpackage Utils
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */

namespace TechDivision\Markman\Utils;

/**
 * TechDivision\Markman\Utils\File
 *
 * File utility which provides additional file operation methods.
 *
 * @category   Tools
 * @package    TechDivision_Markman
 * @subpackage Utils
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */
class File
{
    /**
     * Will write content to a file without the need for a existing path to it.
     * If the path and its folders do not exist they will be created.
     *
     * @param string $path     The path to write the content to
     * @param string $contents Content to write into provided file path
     *
     * @return integer
     */
    public function fileForceContents($path, $contents)
    {
        // Clean the file from the path
        $file = strrchr($path, DIRECTORY_SEPARATOR);
        $path = str_replace($file, '', $path);
        $file = ltrim($file, DIRECTORY_SEPARATOR);

        // Make sure the path exists
        $this->pathForceExistence($path);

        // Finally create our file and fill in the content
        return file_put_contents($path . DIRECTORY_SEPARATOR . $file, $contents);
    }

    /**
     * Will copy a file to a certain path even if it does not exist
     *
     * @param string $sourceFile      The file to copy
     * @param string $destinationFile Target file we want to write to
     *
     * @return integer
     */
    public function fileForceCopy($sourceFile, $destinationFile)
    {
        // Use fileForceContent to create the file complete with its path
        $this->fileForceContents($destinationFile, '');

        // Now copy the file as the path already exists
        return copy($sourceFile, $destinationFile);
    }

    /**
     * Will create a directory path independent from its depth
     *
     * @param string $path A directory only path
     *
     * @return bool
     */
    public function pathForceExistence($path)
    {
        // Split the path into pieces so we can iterate over them
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $path = '';

        // Iterate over the path pieces and build the path up directory for directory
        foreach ($parts as $part) {
            if (!is_dir($path .= DIRECTORY_SEPARATOR . $part)) {
                mkdir($path);
            }
        }

        // Still here? Sounds good
        return true;
    }

    /**
     * Will recursively copy a directory path
     *
     * @param string $src Source path to copy
     * @param string $dst Destination path to copy to
     *
     * @return bool
     */
    public function recursiveCopy($src, $dst)
    {
        // If source is not a directory stop processing
        if (!is_dir($src)) {

            return false;
        }

        // Make sure the destination path exists
        $this->pathForceExistence($dst);

        // Open the source directory to read in files
        $i = new \DirectoryIterator($src);
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dst/" . $f->getFilename());
            } else {
                if (!$f->isDot() && $f->isDir()) {
                    $this->recursiveCopy($f->getRealPath(), "$dst/$f");
                }
            }
        }
    }

    /**
     * Will delete a directory with all its content
     *
     * @param string $dir Directory to delete
     *
     * @return void
     */
    public function recursiveDirectoryDelete($dir)
    {
        // Get the iterators needed to grab all files within the target directory
        $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        // Iterator over all child nodes and delete them all
        foreach ($files as $file) {

            // Do not delete the meta-nodes "." and ".."
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {

                continue;
            }

            // If we got a directory remove it, if we got a file unlink it
            if ($file->isDir()) {

                // Remove the directory
                rmdir($file->getRealPath());

            } else {

                // Unlink the file
                unlink($file->getRealPath());
            }
        }

        // Finally remove the actual directory
        rmdir($dir);
    }

    /**
     * Will produce a relative path which will reverse existing path depth.
     * Example: /path/to/target => ../../../
     *
     * @param string  $path   The path to be reversed
     * @param integer $offset Allows for shortened reverse paths
     *
     * @return string
     */
    public function generateReversePath($path, $offset = 0)
    {
        // Split up the path
        $parts = explode(DIRECTORY_SEPARATOR, $path);

        // Iterate count($parts)-times and create up a string
        $reversePath = '';
        for ($i = 0; $i < count($parts) - $offset; $i++) {

            $reversePath .= '..' . DIRECTORY_SEPARATOR;
        }

        // Return what we got
        return $reversePath;
    }

    /**
     * Will reformat the name of a file to a heading
     *
     * @param string $filename Name of a file (best would be without extension)
     *
     * @return string
     */
    public function filenameToHeading($filename)
    {
        // Scrap the file extension if there is one
        if (strpos($filename, '.') !== false) {

            $filename = strstr($filename, '.', true);
        }

        // Return ucfirst-ed heading
        return ucfirst(str_replace(array('-', '_'), array(' ', '.'), $filename));
    }

    /**
     * Will convert a heading into a filename (without extension)
     *
     * @param string $heading Heading to be converted
     *
     * @return string
     */
    public function headingToFilename($heading)
    {
        // Return all low filename
        return strtolower(str_replace(array(' ', '.'), array('-', '_'), $heading));
    }
}
