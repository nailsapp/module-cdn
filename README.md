# CMS Module for Nails

![license](https://img.shields.io/badge/license-MIT-green.svg)
[![CircleCI branch](https://img.shields.io/circleci/project/github/nailsapp/module-cdn.svg)](https://circleci.com/gh/nailsapp/module-cdn)

This is the CMS module for Nails, it brings content management capability to the app.

http://nailsapp.co.uk/modules/cms

Note: If you are using the Crop functionality of this module it is recommended to use @hellopablo's fork of PHPThumb. There is a bug in the original package which causes black lines to be rendered at the edge of images when cropped to certain dimensions.

To use @hellopablo's fork you must alias the package at the root level `composer.json` (i.e., your project's `composer.json`) file.

    "repositories": [{
        "type": "vcs",
        "url": "https://github.com/hellopablo/PHPThumb"
    }]
