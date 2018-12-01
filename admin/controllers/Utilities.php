<?php

/**
 * Manage the CDN Trash
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

use Nails\Admin\Helper;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Factory;

class Utilities extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', 'nails/module-admin');
        $oNavGroup->setLabel('Utilities');
        $oNavGroup->setIcon('fa-sliders');

        if (userHasPermission('admin:cdn:utilities:findOrphan')) {
            $oNavGroup->addAction('CDN: Find orphaned objects');
        }

        return $oNavGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of permissions which can be configured for the user
     * @return array
     */
    public static function permissions()
    {
        $permissions               = parent::permissions();
        $permissions['findOrphan'] = 'Can find orphans';
        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Find orphaned CDN objects
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:utilities:findOrphan')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        $oInput = Factory::service('Input');
        if ($oInput::isCli()) {
            $this->indexCli();
        } else {

            if ($oInput->post()) {

                //  A little form validation
                $type   = $oInput->post('type');
                $parser = $oInput->post('parser');
                $error  = '';

                if ($type == 'db' && $parser == 'create') {
                    $error = 'Cannot use "Add to database" results parser when finding orphaned database objects.';
                }

                if (empty($error)) {

                    switch ($type) {

                        case 'db':

                            $oCdn                  = Factory::service('Cdn', 'nails/module-cdn');
                            $this->data['orphans'] = $oCdn->findOrphanedObjects();
                            break;

                        //  @TODO
                        case 'file':

                            $this->data['message'] = '<strong>TODO:</strong> find orphaned files.';
                            break;

                        //  Invalid request
                        default:

                            $this->data['error'] = 'Invalid search type.';
                            break;
                    }

                    if (isset($this->data['orphans'])) {

                        switch ($parser) {

                            case 'list':

                                $this->data['success'] = '<strong>Search complete!</strong> your results are show below.';
                                break;

                            //  @todo: keep the unset(), it prevents the table from rendering
                            case 'purge':

                                $this->data['message'] = '<strong>TODO:</strong> purge results.';
                                unset($this->data['orphans']);
                                break;

                            case 'create':

                                $this->data['message'] = '<strong>TODO:</strong> create objects using results.';
                                unset($this->data['orphans']);
                                break;

                            //  Invalid request
                            default:

                                $this->data['error'] = 'Invalid result parse selected.';
                                unset($this->data['orphans']);
                                break;
                        }
                    }

                } else {

                    $this->data['error'] = 'An error occurred. ' . $error;
                }
            }

            // --------------------------------------------------------------------------

            $this->data['page']->title = 'CDN: Find Orphaned Objects';

            // --------------------------------------------------------------------------

            $oAsset = Factory::service('Asset');
            //  @todo (Pablo - 2018-12-01) - Update/Remove/Use minified once JS is refactored to be a module
            $oAsset->load('admin.utilities.orphans.js', 'nails/module-cdn');
            $oAsset->inline(
                'var _Admin_Utilities_Cdn_Orphans = new NAILS_Admin_Utilities_Cdn_Orphans();
                _Admin_Utilities_Cdn_Orphans.init();',
                'JS'
            );

            // --------------------------------------------------------------------------

            Helper::loadView('index');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Find orphaned CDN objects (command line)
     * @return void
     */
    protected function indexCli()
    {
        //  @TODO: Complete CLI functionality for report generating
        echo 'Sorry, this functionality is not complete yet. If you are experiencing timeouts please increase the timeout limit for PHP.';
    }
}
