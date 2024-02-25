<?php

namespace App\Renderer;

use GL\Math\Vec2;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\Pass\FullscreenQuadPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Graphics\TextureOptions;

class MonitorRenderer
{   
    private ShaderProgram $shaderProgram;
    private ShaderProgram $shaderProgramGhosting;
    private ShaderProgram $shaderProgramScreen;

    /**
     * MonoChrome mode
     * This just means the monitor will be rendered in black and white
     */
    public bool $monochrome = true;

    /**
     * Enable / Disable the CRT effect
     */
    public bool $crtEffect = true;

    /**
     * Enable / Disable the ghosting effect
     */
    public bool $ghostingEffect = true;

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     */
    public function __construct(
        GLState $glstate,
        private RenderState $renderState
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
        uniform vec2 screen_size;

        out vec2 tex_coords;

        void main()
        {
            // position are in screen space pixel coordinates
            vec2 npos = position;
            npos.y = screen_size.y - npos.y - size.y;
            vec2 npos_normalized = (2.0 * npos) / screen_size - 1.0;
            vec2 nsize = (2.0 * size) / screen_size;

            vec2 pos = npos_normalized + ((aPos.xy + 1.0) * 0.5) * nsize;

            gl_Position = vec4(pos, aPos.z, 1.0);
            tex_coords = aTexCoord;
        }
        GLSL));

        // also attach a simple fragment shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core

        out vec4 fragment_color;
        in vec2 tex_coords;

        uniform sampler2D u_texture;
        uniform float u_time;
        uniform int u_crt_effect;

        vec2 curve(vec2 uv)
        {
            uv = (uv - 0.5) * 2.0;
            uv *= 1.1;	
            uv.x *= 1.0 + pow((abs(uv.y) / 5.0), 2.0);
            uv.y *= 1.0 + pow((abs(uv.x) / 4.0), 2.0);
            uv  = (uv / 2.0) + 0.5;
            uv =  uv *0.92 + 0.04;
            return uv;
        }

        vec4 get_color(vec2 coords) {
            return texture(u_texture, coords);
        }
        
        /**
         * This is a modified version of the "MattiasCRT" shader from shadertoy
         * https://www.shadertoy.com/view/Ms23DR
         */
        vec4 get_color_crt(vec2 coords)
        {
            vec2 iResolution = vec2(640, 320);

            vec2 uv = curve(coords);
            vec3 oricol = get_color(coords).xyz;
            vec3 col;
            float x = sin(0.3*u_time+uv.y*21.0)*sin(0.7*u_time+uv.y*29.0)*sin(0.3+0.33*u_time+uv.y*31.0)*0.0017;

            col.r = get_color(vec2(x+uv.x+0.001,uv.y+0.001)).x+0.05;
            col.g = get_color(vec2(x+uv.x+0.000,uv.y-0.002)).y+0.05;
            col.b = get_color(vec2(x+uv.x-0.002,uv.y+0.000)).z+0.05;
            col.r += 0.08*get_color(0.75*vec2(x+0.025, -0.027)+vec2(uv.x+0.001,uv.y+0.001)).x;
            col.g += 0.05*get_color(0.75*vec2(x+-0.022, -0.02)+vec2(uv.x+0.000,uv.y-0.002)).y;
            col.b += 0.08*get_color(0.75*vec2(x+-0.02, -0.018)+vec2(uv.x-0.002,uv.y+0.000)).z;

            col = clamp(col*0.6+0.4*col*col*1.0,0.0,1.0);

            float vig = (0.0 + 1.0*16.0*uv.x*uv.y*(1.0-uv.x)*(1.0-uv.y));
            col *= vec3(pow(vig,0.3));

            col *= vec3(0.95,1.05,0.95);
            col *= 2.8;

            float scans = clamp( 0.35+0.35*sin(3.5*u_time+uv.y*iResolution.y*1.5), 0.0, 1.0);
            
            float s = pow(scans,1.7);
            col = col*vec3( 0.4+0.7*s) ;

            col *= 1.0+0.01*sin(110.0*u_time);
            col*=1.0-0.65*vec3(clamp((mod(coords.x, 2.0)-1.0)*2.0,0.0,1.0));

            return vec4(col, 1.0);
        }

        void main()
        {   
            if (u_crt_effect == 0) {
                fragment_color = get_color(tex_coords);
                return;
            }
            fragment_color = get_color_crt(tex_coords);
        }
        GLSL));
        $this->shaderProgram->link();

        /**
         * Ghosting shader
         */
        $this->shaderProgramGhosting = new ShaderProgram($glstate);
        $this->shaderProgramGhosting->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core

        layout (location = 0) in vec3 aPos;
        layout (location = 1) in vec2 aTexCoord;

        out vec2 tex_coords;

        void main()
        {
            gl_Position = vec4(aPos, 1.0);
            tex_coords = aTexCoord;
        }
        GLSL));

        $this->shaderProgramGhosting->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core

        out vec4 fragment_color;
        in vec2 tex_coords;

        uniform sampler2D u_texture;

        void main()
        {
            vec4 color = texture(u_texture, tex_coords);

            // slightly darken the color
            //color *= 0.995;
            color *= 0.99;

            fragment_color = color;
        }
        GLSL));

        $this->shaderProgramGhosting->link();

        /**
         * Screen shader
         */
        $this->shaderProgramScreen = new ShaderProgram($glstate);
        $this->shaderProgramScreen->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core

        layout (location = 0) in vec3 aPos;
        layout (location = 1) in vec2 aTexCoord;

        out vec2 tex_coords;

        void main()
        {
            gl_Position = vec4(aPos, 1.0);
            tex_coords = aTexCoord;
        }
        GLSL));

        $this->shaderProgramScreen->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core

        out vec4 fragment_color;
        in vec2 tex_coords;

        uniform usampler2D u_texture;
        uniform int u_monochrome;

        vec4 get_color(vec2 coords) {
            uint pixel = texture(u_texture, coords * vec2(1, -1)).r;

            // monochrome mode here just means either on or off
            if (u_monochrome == 1) {
                return vec4(pixel > uint(0) ? 1.0 : 0.0);
            }

            // unpack the pixel into 3,3,2 bits
            uint r = (pixel >> 5u) & 0x07u;
            uint g = (pixel >> 2u) & 0x07u;
            uint b = pixel & 0x03u;
        
            // normalize the color
            float red = float(r) / 7.0;
            float green = float(g) / 7.0;
            float blue = float(b) / 3.0;
            
            return vec4(red, green, blue, pixel > uint(0) ? 1.0 : 0.0);
        }
        
        void main()
        {
            fragment_color = get_color(tex_coords);
        }

        GLSL));

        $this->shaderProgramScreen->link();
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
        $pipeline->addPass(new CallbackPass(
            'ScreenGhosting',
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use ($texture, $renderTarget) {

                $passData = $data->create(MonitorPassData::class);

                $nearestOpt = new TextureOptions;
                $nearestOpt->minFilter = GL_NEAREST;
                $nearestOpt->magFilter = GL_NEAREST;

                $passData->ghostingTarget = $pipeline->createRenderTarget('ghosting_fade', $texture->width, $texture->height);
                $passData->ghostingTexture = $pipeline->createColorAttachment($passData->ghostingTarget, 'screen_fade', $nearestOpt);

                $passData->screenTarget = $pipeline->createRenderTarget('screen', $texture->width, $texture->height);
                $passData->screenTexture = $pipeline->createColorAttachment($passData->screenTarget, 'screen', $nearestOpt);

                $pipeline->writes($pass, $passData->ghostingTarget);
                $pipeline->writes($pass, $passData->screenTarget);
                $pipeline->reads($pass, $texture);

                // clear the ghosting target
                $pipeline->addPass(new ClearPass($passData->ghostingTarget));

                // then render a ghost of the previous frame on the ghosting target
                $ghostPass = new FullscreenQuadPass(
                    $passData->ghostingTarget,
                    $passData->screenTexture,
                    $this->shaderProgramGhosting,
                );

                $pipeline->addPass($ghostPass);

                // then render the monitor on the ghosting target aswell
                $screenPass = new FullscreenQuadPass(
                    $passData->ghostingTarget,
                    $texture,
                    $this->shaderProgramScreen,
                );
                $screenPass->shouldBlend = $this->ghostingEffect;
                $screenPass->extraUniforms['u_monochrome'] = (int) $this->monochrome;

                $pipeline->addPass($screenPass);

                // clear the screen target and render the ghosted frame on the screen target
                // so we can use it in the next frame
                $pipeline->addPass(new ClearPass($passData->screenTarget));
                $copyPass = new FullscreenQuadPass(
                    $passData->screenTarget,
                    $passData->ghostingTexture,
                    $this->shaderProgramGhosting,
                );
                $pipeline->addPass($copyPass);

                // clear the actual render target
                $pipeline->addPass(new ClearPass($renderTarget));

                // render the monitor on the actual render target
                $quadPass = new FullscreenQuadPass(
                    $renderTarget,
                    $passData->ghostingTexture,
                    $this->shaderProgram,
                );

                $monitorPos = $this->renderState->getMonitorPosition();
                $monitorSize = $this->renderState->getMonitorSize();

                // var_dump($monitorPos, $monitorSize); die;

                $quadPass->extraUniforms['position'] = $monitorPos;
                $quadPass->extraUniforms['size'] = $monitorSize;
                $quadPass->extraUniforms['screen_size'] = new Vec2(
                    $renderTarget->width / $renderTarget->contentScaleX, 
                    $renderTarget->height / $renderTarget->contentScaleY
                );



                // if ($this->renderState->fullscreenMonitor) {
                //     $quadPass->extraUniforms['position'] = new Vec2(0, 0);
                //     $quadPass->extraUniforms['size'] = new Vec2(1, 1);
                // } else {
                //     $quadPass->extraUniforms['position'] = new Vec2(0.5, 0.5);
                //     $quadPass->extraUniforms['size'] = new Vec2(0.5, 0.5);
                // }

                $quadPass->extraUniforms['u_time'] = glfwGetTime();
                $quadPass->extraUniforms['u_crt_effect'] = (int) $this->crtEffect;

                $pipeline->addPass($quadPass);

            },
            function(PipelineContainer $data, PipelineResources $resources) use ($texture) {
                // activate the screen render target to ensure the screen
                // texture is available in the next frame
                $passData = $data->get(MonitorPassData::class);
                $resources->activateRenderTarget($passData->screenTarget);
            },
        ));   
    }
}
