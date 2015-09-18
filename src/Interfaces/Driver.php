<?php

namespace Nails\Cdn\Interfaces;

interface Driver
{
    //  Object methods
    public function object_create($data);
    public function object_exists($filename, $bucket);
    public function object_destroy($object, $bucket);
    public function object_local_path($bucket, $filename);

    //  Bucket methods
    public function bucket_create($bucket);
    public function bucket_destroy($bucket);

    //  URL methods
    public function url_serve($object, $bucket, $forceDownload);
    public function url_serve_scheme($forceDownload);
    public function url_serve_zipped($objectIds, $hash, $filename);
    public function url_serve_zipped_scheme();
    public function url_thumb($object, $bucket, $width, $height);
    public function url_thumb_scheme();
    public function url_scale($object, $bucket, $width, $height);
    public function url_scale_scheme();
    public function url_placeholder($width = 100, $height = 100, $border = 0);
    public function url_placeholder_scheme();
    public function url_blank_avatar($width = 100, $height = 100, $sex = 'male');
    public function url_blank_avatar_scheme();
    public function url_expiring($object, $bucket, $expires);
    public function url_expiring_scheme();
}