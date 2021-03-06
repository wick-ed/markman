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
 * @category  Tools
 * @package   TechDivision_Markman
 * @author    Bernhard Wick <b.wick@techdivision.com>
 * @copyright 2014 TechDivision GmbH - <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.techdivision.com/
 */

namespace TechDivision\Markman\Compilers;

use TechDivision\Markman\Compilers\Post\UsabilityPostCompiler;
use TechDivision\Markman\Compilers\Pre\GithubPreCompiler;
use TechDivision\Markman\Utils\File;
use TechDivision\Markman\Utils\Parsedown;
use TechDivision\Markman\Utils\Template;
use TechDivision\Markman\Config;

/**
 * TechDivision\Markman\Compiler
 *
 * Compiler class using Parsedown to create an exact html copy of the online markdown documentation and
 * its structure.
 *
 * @category  Tools
 * @package   TechDivision_Markman
 * @author    Bernhard Wick <b.wick@techdivision.com>
 * @copyright 2014 TechDivision GmbH - <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.techdivision.com/
 */
class Compiler extends AbstractCompiler
{
    /**
     * The actual compiler
     *
     * @var \Parsedown $compiler
     */
    protected $compiler;

    /**
     * The template utility which helps creating the output
     *
     * @var \TechDivision\Markman\Utils\Template $templateUtil
     */
    protected $templateUtil;

    /**
     * Extensions of files we will parse and regenerate using our compiler
     *
     * @var array $allowedExtensions
     */
    protected $allowedExtensions;

    /**
     * Extensions of files we need to preserve and therefor copy to the compiled documentation.
     * Images for example.
     *
     * @var array $preservedExtensions
     */
    protected $preservedExtensions;

    /**
     * A list of compiler which have to run prior to this one
     *
     * @var array $preCompilers
     */
    protected $preCompilers = array();

    /**
     * A list of compiler which have to run after this one
     *
     * @var array $postCompilers
     */
    protected $postCompilers = array();

    /**
     * Default constructor
     *
     * @param \TechDivision\Markman\Config $config The project's configuration instance
     */
    public function __construct(Config $config)
    {
        // Save the configuration
        $this->config = $config;

        // Get ourselves an instance of the Parsedown compiler
        $this->compiler = new \Parsedown();
        $this->templateUtil = new Template($config);

        // Prefill the allowed and preserved extensions
        $this->allowedExtensions = array_flip($this->config->getValue(Config::PROCESSED_EXTENSIONS));
        $this->preservedExtensions = array_flip($this->config->getValue(Config::PRESERVED_EXTENSIONS));

        // We might want to add some pre- or post-compilers to our stack, based on what kind of configuration we got
        $this->preCompilers = array();
        $this->postCompilers = array();

        // Only thing we know right now is the Github pre-compiler
        // @TODO add a more general compiler handling
        if ($this->config->getValue(Config::LOADER_HANDLER) === 'github') {

            $this->preCompilers = array(new GithubPreCompiler($config));
        }

        // A post-compiler we always need is the usability one
        $this->postCompilers[] = new UsabilityPostCompiler($config);
    }

    /**
     * Will compile the documentation file structure at the given tmp path.
     * Will generate the same file structure with turning markdown into html.
     * Will also generate navigational elements and embed the documentation content into the configured
     * template.
     *
     * @param string $tmpFilesPath   Path to the temporary, raw, documentation
     * @param string $targetBasePath Path to write the documentation to
     * @param string $currentVersion The version we are currently compiling for
     * @param array  $versions       Versions a documentation exists for
     *
     * @return bool
     */
    public function compile($tmpFilesPath, $targetBasePath, $currentVersion, $versions)
    {
        // Is there anything useful here?
        if (!is_readable($tmpFilesPath)) {

            return false;
        }

        // First of all we need a version file
        $this->compileVersionSwitch($versions, $currentVersion);

        // Path prefix which points into the generated folder with the specific version we are compiling right now
        $pathPrefix = $this->config->getValue(Config::BUILD_PATH) . DIRECTORY_SEPARATOR .
            $targetBasePath . DIRECTORY_SEPARATOR . $currentVersion;

        // Now let's generate the navigation
        $this->generateNavigation($tmpFilesPath, $pathPrefix);

        // Added version switcher and navigation elements
        $this->templateUtil->getTemplate(
            array(
                '{navigation-element}' => file_get_contents(
                    $pathPrefix .
                    $this->config->getValue(Config::NAVIGATION_FILE_NAME) . '.html'
                ),
                '{project-site}' => $this->config->getValue(Config::PROJECT_SITE)
            ),
            true
        );

        // Get an iterator over the part of the directory we want
        $iterator = $this->getDirectoryIterator($tmpFilesPath);

        // We do not have to read the version switcher so many times
        $versionSwitcherContent = file_get_contents(
            $this->config->getValue(Config::BUILD_PATH) . DIRECTORY_SEPARATOR .
            $this->config->getValue(Config::PROJECT_NAME) . DIRECTORY_SEPARATOR .
            $currentVersion . DIRECTORY_SEPARATOR .
            $this->config->getValue(Config::VERSION_SWITCHER_FILE_NAME) . '.html'
        );

        // Compile all the files
        $fileUtil = new File();
        foreach ($iterator as $file) {

            // Create the name of the target file relative to the containing base directory (tmp or build)
            $targetFile = str_replace($tmpFilesPath, '', $file);

            // Apply any name mapping there might be
            if (count($this->config->getValue(Config::FILE_MAPPING)) > 0) {

                // Split up the mapping and apply it
                $haystacks = array_keys($this->config->getValue(Config::FILE_MAPPING));
                $targetFile = str_replace($haystacks, $this->config->getValue(Config::FILE_MAPPING), $targetFile);
            }

            // If we have to preserve the file we do not want to compile nor do we want to have it embedded in
            // the template
            if (isset($this->preservedExtensions[strtolower($file->getExtension())])) {

                // We will only copy the file without altering its content
                $fileUtil->fileForceCopy($file, $pathPrefix . $targetFile);

            } else {

                // If the file has a markdown extension we will compile it, if it is something we have to preserve we
                // will do so, if not we will skip it
                $rawContent = '';
                if (isset($this->allowedExtensions[$file->getExtension()])) {

                    // Do the compilation
                    $rawContent = $this->compileFile($file);

                    // Now change the extension for the ones not already covered by any file mapping
                    $targetFile = str_replace($file->getExtension(), 'html', $targetFile);

                } elseif (!isset($this->preservedExtensions[strtolower($file->getExtension())])) {

                    continue;

                }

                // Create the html content. We will need the reverse path of the current file here.
                $reversePath = $fileUtil->generateReversePath($targetFile, 1);

                // Now fill the template a last time and retrieve the complete template
                $content =  $this->templateUtil->getTemplate(
                    array(
                        '{version-switcher-element}' => $versionSwitcherContent,
                        '{content}' => $rawContent,
                        '{relative-base-url}' => $reversePath . Template::VENDOR_DIR,
                        '{navigation-base}' =>  '',
                        '{version-switch-base}' => $reversePath,
                        '{version-switch-file}' => $targetFile
                    )
                );

                // Clear the file specific changes of the template
                $this->templateUtil->clearTemplate();

                // Save the processed (or not) content to a file.
                // Recreate the path the file originally had
                $fileUtil->fileForceContents($pathPrefix . $targetFile, $content);
            }
        }
    }

    /**
     * Will compile a certain file including any needed pre- and post-compilers
     *
     * @param \SplFileInfo $file The file which content has to be compiled
     *
     * @return string
     */
    protected function compileFile($file)
    {
        // Get the content of the file
        $rawContent = file_get_contents($file);

        // Run all the pre-compilers before doing anything
        foreach ($this->preCompilers as $preCompiler) {

            $rawContent = $preCompiler->compile($rawContent);
        }

        // Now let the actual compiler do its work
        $rawContent = $this->compiler->text($rawContent);

        // Let's also run all the post-compilers here
        foreach ($this->postCompilers as $postCompiler) {

            $rawContent = $postCompiler->compile($rawContent);
        }

        // Return what we got
        return $rawContent;
    }

    /**
     * Will generate a separate file containing a html list of all versions a documentation has
     *
     * @param array  $versions       Array of versions
     * @param string $currentVersion The version we are currently compiling for
     *
     * @return void
     */
    public function compileVersionSwitch(array $versions, $currentVersion)
    {
        // Build up the html
        $html = '<ul role="navigation" class="nav sf-menu">
        <li class="dropdown sf-with-ul"><a href="#" data-toggle="dropdown" class="dropdown-toggle sf-with-ul">Versions
        <span class="sf-sub-indicator fa fa-angle-down"></span></a>
        <ul id="' . $this->config->getValue(Config::VERSION_SWITCHER_FILE_NAME) . '" class="dropdown-menu">';
        foreach ($versions as $version) {

            // Build up the html, but make sure to set the current version as active
            $html .= '<li';

            // If this is the current version we have to set it as active
            if ($version->getName() === $currentVersion) {

                $html .= ' class="active"';
            }

            // Now comes the rest of the html
            $html .= ' node="' . $version->getName() . '">
            <a href="{version-switch-base}' .
                $version->getName() . '{version-switch-file}" class="sf-with-ul">' . $version->getName() . '
                </a></li>';

        }
        $html .= '</ul></li></ul>';

        // Write html to file
        $fileUtil = new File();
        $fileUtil->fileForceContents(
            $this->config->getValue(Config::BUILD_PATH) . DIRECTORY_SEPARATOR .
            $this->config->getValue(Config::PROJECT_NAME) . DIRECTORY_SEPARATOR .
            $currentVersion . DIRECTORY_SEPARATOR .
            $this->config->getValue(Config::VERSION_SWITCHER_FILE_NAME) . '.html',
            $html
        );
    }

    /**
     * Will generate a navigation for a certain folder structure
     *
     * @param string $srcPath    Path to get the structure from
     * @param string $targetPath Path to write the result to
     *
     * @return void
     */
    protected function generateNavigation($srcPath, $targetPath)
    {
        // Write to file
        $fileUtil = new File();
        $fileUtil->fileForceContents(
            $targetPath . $this->config->getValue(Config::NAVIGATION_FILE_NAME) . '.html',
            '<nav>' .
            '<h2><i class="fa fa-reorder floatRight cursorPointer"></i></h2>
                <ul>' . $this->generateRecursiveList(new \DirectoryIterator($srcPath), '') . '</ul>
            </nav>'
        );
    }

    /**
     * Will recursively generate a html list based on a directory structure
     *
     * @param \DirectoryIterator $dir      The directory to add to the list
     * @param string             $nodePath The path of the nodes already collected
     *
     * @return string
     */
    protected function generateRecursiveList(\DirectoryIterator $dir, $nodePath)
    {
        // We will need a flipped file mapping as we work from the tmp dir
        $fileMapping = array_flip($this->config->getValue(Config::FILE_MAPPING));
        $mappedIndexFile = $fileMapping['index.html'];

        $out = '';
        $fileUtil = new File();
        $parsedownUtil = new Parsedown();
        foreach ($dir as $node) {

            // Create the link path
            $linkPath = str_replace('.md', '.html', $this->config->getValue(Config::NAVIGATION_BASE) . $nodePath . $node);

            // If we got a directory we have to go deeper. If not we can add another link
            if ($node->isDir() && !$node->isDot()) {

                // Stack up the node path as we need for out links
                $nodePath .= $node . DIRECTORY_SEPARATOR;

                // Build up the link structure
                $nodeName = '<a href="{navigation-base}' . $linkPath . DIRECTORY_SEPARATOR .
                    $this->config->getValue(Config::INDEX_FILE_NAME) . '">' . $fileUtil->filenameToHeading($node) .
                    '</a>';

                // Make a recursion with the new path
                $out .= '<li  class="icon-thin-arrow-left" node="' . $node . '">' . $nodeName . '

                        <h2>' . $fileUtil->filenameToHeading($node) . '</h2>
                        <ul>' .
                    $this->generateRecursiveList(new \DirectoryIterator($node->getPathname()), $nodePath) .
                    '</ul></li>';

                // Clean the last path segment as we do need it within this loop
                $nodePath = str_replace($node . DIRECTORY_SEPARATOR, '', $nodePath);

            } elseif ($node->isFile() && isset($this->allowedExtensions[$node->getExtension()])) {
                // A file is always a leaf, so it cannot be an ul element

                // We will skip index files as actual leaves
                if ($node == $mappedIndexFile) {

                    continue;
                }

                // Get the node's name (in a heading format)
                $nodeName = $fileUtil->filenameToHeading($node);

                // Do we have an markdown file? If so we will check for any markdown headings
                $headingBlock = '';
                if (isset($this->allowedExtensions[$node->getExtension()])) {

                    // We need the headings within the file
                    $headings = $parsedownUtil->getHeadings(
                        file_get_contents($dir->getPathname()),
                        $this->config->getValue(Config::NAVIGATION_HEADINGS_LEVEL)
                    );

                    // Create the list of headings as a ul/li list
                    $headingBlock = $this->generateHeadingBlock($headings, $nodeName);
                }

                // Create the actual leaf
                $out .= '<li node="' . strstr($node, ".", true) . '"><a href="{navigation-base}' .
                    $linkPath . '">' . $nodeName . '</a>' .
                    $headingBlock . '</li>';
            }
        }

        // Return the menu
        return $out;
    }

    /**
     * Will generate an html list for given headings
     *
     * @param array  $headings Headings to add to the block
     * @param string $nodeName Name of the node as a block heading
     *
     * @return string
     */
    protected function generateHeadingBlock(array $headings, $nodeName)
    {
        // We need a file util to create URL ready anchors
        $fileUtil = new File();

        // Iterate over the headings and build up a li list
        $html = '       <h2>' . $nodeName . '</h2>
                        <ul>';

        // Iterate over all headings and build up the "li" list
        foreach ($headings as $heading) {

            $html .= '<li class="heading"><a href="#' . $fileUtil->headingToFilename($heading) .
                '">' . $heading . '</a></li>';
        }

        return $html . '</ul>';
    }

    /**
     * Will Return an iterator over a set of files determined by a list of directories to iterate over
     *
     * @param string $paths List of directories to iterate over
     *
     * @return \Iterator
     */
    protected function getDirectoryIterator($paths)
    {
        // If we are no array, we have to make it one
        if (!is_array($paths)) {

            $paths = array($paths);
        }

        // As we might have several rootPaths we have to create several RecursiveDirectoryIterators.
        $directoryIterators = array();
        foreach ($paths as $path) {

            $directoryIterators[] = new \RecursiveDirectoryIterator(
                $path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            );
        }

        // We got them all, now append them onto a new RecursiveIteratorIterator and return it.
        $recursiveIterator = new \AppendIterator();
        foreach ($directoryIterators as $directoryIterator) {

            // Append the directory iterator
            $recursiveIterator->append(
                new \RecursiveIteratorIterator(
                    $directoryIterator,
                    \RecursiveIteratorIterator::SELF_FIRST,
                    \RecursiveIteratorIterator::CATCH_GET_CHILD
                )
            );
        }

        return $recursiveIterator;
    }
}
