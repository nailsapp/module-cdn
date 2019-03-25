<?php

/**
 * This service manages the CDN storage drivers
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Service;

use Nails\Common\Model\BaseDriver;

class StorageDriver extends BaseDriver
{
    protected $sModule         = 'nails/module-cdn';
    protected $sType           = 'storage';
    protected $bEnableMultiple = false;
}