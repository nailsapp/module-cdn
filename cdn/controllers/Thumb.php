<?php

/**
 * This class handles the "thumb" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

require_once 'crop.php';

/**
 * Class Thumb
 */
class Thumb extends Crop
{
    /**
     * Generate a thumbnail
     **/
    public function index(string $sCropMethod = 'CROP')
    {
        return parent::index($sCropMethod);
    }
}
