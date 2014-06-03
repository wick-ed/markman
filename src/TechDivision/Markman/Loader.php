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
 * @package    TechDivision
 * @subpackage Markman
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */

namespace TechDivision\Markman;

use TechDivision\Markman\Handler\GithubHandler;


/**
 * TechDivision\Markman\Loader
 *
 * Will load the a certain repository from github
 *
 * @category   Tools
 * @package    TechDivision
 * @subpackage Markman
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */
class Loader
{
    /**
     * @var Handler\GithubHandler $handler <REPLACE WITH FIELD COMMENT>
     */
    protected $handler;

    /**
     * @param $api
     */
    public function __construct($api, $handlerString)
    {
        $this->handler = new GithubHandler();
        $this->handler->connect($handlerString);
    }

    /**
     *
     */
    public function getVersions()
    {
        return $this->handler->getVersions();
    }

    public function getDocByVersion($version)
    {
        return $this->handler->getDocByVersion($version);
    }

    public function getSystemPathModifier()
    {
        return $this->handler->getSystemPathModifier();
    }
}

 