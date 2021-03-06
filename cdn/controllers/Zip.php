<?php

/**
 * This class handles the "zip" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Cdn\Constants;
use Nails\Cdn\Controller\Base;
use Nails\Factory;

class Zip extends Base
{
    /**
     * Serve a zip file containing objects
     *
     * @return void
     */
    public function index()
    {
        //  Decode the token
        $oUri     = Factory::service('Uri');
        $ids      = $oUri->segment(3);
        $hash     = $oUri->segment(4);
        $filename = urldecode($oUri->segment(5));

        if ($ids && $hash) {

            //  Check the hash
            $oCdn    = Factory::service('Cdn', Constants::MODULE_SLUG);
            $objects = $oCdn->verifyUrlServeZippedHash($hash, $ids, $filename);

            if ($objects) {

                //  Define the cache file
                $this->cdnCacheFile = 'cdn-zip-' . $hash . '.zip';

                /**
                 * Check the request headers; avoid hitting the disk at all if possible. If
                 * the Etag matches then send a Not-Modified header and terminate execution.
                 */

                if ($this->serveNotModified($this->cdnCacheFile)) {
                    return;
                }

                // --------------------------------------------------------------------------

                /**
                 * The browser does not have a local cache (or it's out of date) check the cache
                 * to see if this image has been processed already; serve it up if it has.
                 */

                if (!$this->cdnCache->exists($this->cdnCacheFile)) {

                    /**
                     * Cache object does not exist, fetch the originals, zip them and save a
                     * version in the cache bucket..
                     *
                     * Fetch the files to use, if any one doesn't exist any more then this zip
                     * file should fall over.
                     */

                    $usefiles   = [];
                    $useBuckets = false;
                    $prevBucket = '';

                    foreach ($objects as $obj) {

                        $temp           = new stdClass();
                        $temp->path     = $oCdn->objectLocalPath($obj->id);
                        $temp->filename = $obj->file->name->human;
                        $temp->bucket   = $obj->bucket->label;

                        if (!$temp->path) {
                            $this->serveBadSrc([
                                'error' => 'Object "' . $obj->filename . '" does not exist',
                            ]);
                        }

                        if (!$useBuckets && $prevBucket && $prevBucket !== $obj->bucket->id) {
                            $useBuckets = true;
                        }

                        $prevBucket = $obj->bucket->id;
                        $usefiles[] = $temp;
                    }

                    // --------------------------------------------------------------------------

                    //  Time to start Zipping!
                    //  @todo (Pablo - 2019-07-19) - Use Zip service
                    get_instance()->load->library('zip');

                    //  Save to the zip
                    foreach ($usefiles as $file) {

                        $name = $useBuckets ? $file->bucket . '/' . $file->filename : $file->filename;
                        get_instance()->zip->add_data($name, file_get_contents($file->path));
                    }

                    //  Save the Zip to the cache directory
                    get_instance()->zip->archive($this->cdnCacheDir . $this->cdnCacheFile);
                }

                $this->serveFromCache($this->cdnCacheFile);

            } else {
                $this->serveBadSrc([
                    'error' => 'Could not verify token',
                ]);
            }

        } else {
            $this->serveBadSrc([
                'error' => 'Missing parameters',
            ]);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Handles bad requests
     *
     * @param array $params
     */
    protected function serveBadSrc(array $params)
    {
        $error = $params['error'];

        $oInput = Factory::service('Input');
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Content-Type: application/json', true);
        header($oInput->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);

        // --------------------------------------------------------------------------

        $out = [
            'status'  => 400,
            'message' => 'Invalid Request',
        ];

        if (!empty($error)) {
            $out['error'] = $error;
        }

        echo json_encode($out);

        // --------------------------------------------------------------------------

        /**
         * Kill script, th, th, that's all folks. Stop the output class from hijacking
         * our headers and setting an incorrect Content-Type
         */


        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Map all requests to index()
     *
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
