<?php

namespace App\Renderer;

use GL\Math\Vec2;
use VISU\Graphics\Viewport;

class RenderState
{
    /**
     * Viewport where to place the GUI elements
     */
    public Viewport $viewport;

    /**
     * Offset the monitor is moved from the top left corner in non fullscreen mode
     */
    public Vec2 $monitorOffest;

    /**
     * Should the monitor be rendered in fullscreen?
     * (Otherwise it will be rendered in the top right corner)
     */
    public bool $fullscreenMonitor = false;

    public function __construct() {
        $this->viewport = new Viewport(0, 1920, 1080, 0, 1, 1);
        $this->monitorOffest = new Vec2(25.0, -25.0);
    }

    /**
     * Returns the position of the monitor in screen space
     */
    public function getMonitorPosition() : Vec2
    {
        if ($this->fullscreenMonitor) {
            return new Vec2(0, 0);
        }

        $pos = new Vec2($this->viewport->width * 0.5, $this->viewport->top);

        // move the mointor a bit into the viewport
        $pos = $pos - $this->monitorOffest;
        
        return $pos;
    }

    /**
     * Returns the size of the monitor in screen space
     */
    public function getMonitorSize() : Vec2
    {
        if ($this->fullscreenMonitor) {
            return new Vec2($this->viewport->width, $this->viewport->height);
        }

        $width = $this->viewport->width * 0.5;
        // as chip-8 is 64x32, we want to keep the aspect ratio
        $height = $width * 0.5;

        return new Vec2($width, $height);
    }
}