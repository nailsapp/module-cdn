<?php

/**
 * This model handles interactions with the module's "object" table.
 * @todo        Integrate this properly with the library
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model;

use Nails\Common\Model\Base;
use Nails\Factory;

class CdnObject extends Base
{
    const RESOURCE_NAME = 'CdnObject';
    const RESOURCE_PROVIDER = 'nails/module-cdn';

    /**
     * Object constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->table             = NAILS_DB_PREFIX . 'cdn_object';
        $this->defaultSortColumn = 'modified';
        $this->defaultSortOrder  = 'desc';
        $this->tableLabelColumn  = 'filename_display';
        $this->searchableFields  = [
            'id',
            'filename',
            'filename_display',
        ];
        $this->addExpandableField([
            'trigger'   => 'bucket',
            'type'      => self::EXPANDABLE_TYPE_SINGLE,
            'property'  => 'bucket',
            'model'     => 'Bucket',
            'provider'  => 'nails/module-cdn',
            'id_column' => 'bucket_id',
        ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an object by it's MD5 hash
     *
     * @param string $sHash The MD5 hash to look for
     * @param array  $aData Any additional data to pass in
     *
     * @return \stdClass|null
     */
    public function getByMd5Hash($sHash, array $aData = [])
    {
        return $this->getByColumn('md5_hash', $sHash, $aData);
    }

    // --------------------------------------------------------------------------

    protected function formatObject(
        &$oObj,
        array $aData = [],
        array $aIntegers = [],
        array $aBools = [],
        array $aFloats = []
    ) {
        $aIntegers[] = 'img_width';
        $aIntegers[] = 'img_height';
        $aIntegers[] = 'serves';
        $aIntegers[] = 'downloads';
        $aIntegers[] = 'thumbs';
        $aIntegers[] = 'scales';
        $aIntegers[] = '';
        $aIntegers[] = '';

        $aBools[] = 'is_animated';

        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);
    }
}
