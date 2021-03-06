<?php

namespace Nails\Cdn\Helper\Picture;

use Nails\Cdn\Service\Cdn;

/**
 * Class Source
 *
 * @package Nails\Cdn\Helper\Picture
 * @link    https://docs.nailsapp.co.uk/modules/cdn/helpers/picture
 */
class Source
{
    /** @var Cdn */
    protected $oCdn;

    /** @var int */
    protected $iCdnObjectId;

    /** @var int */
    protected $iWidth;

    /** @var int */
    protected $iHeight;

    /** @var int */
    protected $iBreakpoint;

    /** @var float */
    protected $fDensity;

    // --------------------------------------------------------------------------

    /**
     * Source constructor.
     *
     * @param Cdn        $oCdn
     * @param int        $iCdnObjectId
     * @param int        $iWidth
     * @param int        $iHeight
     * @param int|null   $iBreakpoint
     * @param float|null $fDensity
     */
    public function __construct(
        Cdn $oCdn,
        int $iCdnObjectId,
        int $iWidth,
        int $iHeight,
        int $iBreakpoint = null,
        float $fDensity = null
    ) {
        $this->oCdn         = $oCdn;
        $this->iCdnObjectId = $iCdnObjectId;
        $this->iWidth       = $iWidth;
        $this->iHeight      = $iHeight;
        $this->iBreakpoint  = $iBreakpoint;
        $this->fDensity     = $fDensity;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the <source> element
     *
     * @return string
     */
    public function generate(): string
    {
        return sprintf(
            '<source srcset="%s" media="%s">',
            implode(' ', array_filter([
                $this->oCdn->urlCrop($this->iCdnObjectId, $this->iWidth, $this->iHeight),
                $this->getDensityString(),
            ])),
            $this->getBreakpointString()
        );
    }

    // --------------------------------------------------------------------------

    protected function getBreakpointString(): ?string
    {
        return $this->iBreakpoint ? '(min-width: ' . $this->iBreakpoint . 'px)' : null;
    }

    // --------------------------------------------------------------------------

    protected function getDensityString(): ?string
    {
        return $this->fDensity ? $this->fDensity . 'x' : null;
    }
}
