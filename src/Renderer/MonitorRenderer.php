<?php

namespace App\Renderer;

use GL\Math\Vec2;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\Pass\FullscreenQuadPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

class MonitorRenderer
{   
    private ShaderProgram $shaderProgram;

    /**
     * If the monitor should be rendered in fullscreen
     * (Otherwise it will be rendered in the top right corner)
     */
    public bool $fullscreen = false;    

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     */
    public function __construct(
        GLState $glstate,
    )
    {
        // create the shader program
        $this->shaderProgram = new ShaderProgram($glstate);

        // attach a simple vertex shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core

        layout (location = 0) in vec3 aPos;
        layout (location = 1) in vec2 aTexCoord;

        // im lazy to so just transform in the shader via uniforms
        uniform vec2 position;
        uniform vec2 size;

        out vec2 tex_coords;

        void main()
        {
            vec2 pos = aPos.xy * size + position;

            gl_Position = vec4(pos, aPos.z, 1.0);
            tex_coords = aTexCoord;
        }
        GLSL));

        // also attach a simple fragment shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core

        out vec4 fragment_color;
        in vec2 tex_coords;

        uniform usampler2D u_texture;

        void main()
        {             
            uint pixel = texture(u_texture, tex_coords).r;     

            // unpack the pixel into 3,3,2 bits
            uint r = (pixel >> 5u) & 0x07u;
            uint g = (pixel >> 2u) & 0x07u;
            uint b = pixel & 0x03u;
        
            // normalize the color
            float red = float(r) / 7.0;
            float green = float(g) / 7.0;
            float blue = float(b) / 3.0;
            
            fragment_color = vec4(red, green, blue, 1.0);
        }
        GLSL));
        $this->shaderProgram->link();
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param RenderTargetResource $renderTarget
     * @param TextureResource $texture
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
        TextureResource $texture
    ) : void
    {
        $pipeline->addPass(new ClearPass($renderTarget));

        $quadPass = new FullscreenQuadPass(
            $renderTarget,
            $texture,
            $this->shaderProgram,
        );

        if ($this->fullscreen) {
            $quadPass->extraUniforms['position'] = new Vec2(0, 0);
            $quadPass->extraUniforms['size'] = new Vec2(1, 1);
        } else {
            $quadPass->extraUniforms['position'] = new Vec2(0.5, 0.5);
            $quadPass->extraUniforms['size'] = new Vec2(0.5, 0.5);
        }

        $pipeline->addPass($quadPass);
    }
}
