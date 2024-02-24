<?php

namespace App\Renderer;

use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;

class MonitorPassData
{      
    public RenderTargetResource $ghostingTarget;
    public TextureResource $ghostingTexture; 
    public RenderTargetResource $screenTarget;
    public TextureResource $screenTexture;
}