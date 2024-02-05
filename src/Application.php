<?php 

namespace App;

use App\Renderer\MonitorRenderer;
use Error;
use GL\VectorGraphics\{VGAlign, VGColor, VGContext};

use VISU\Graphics\{RenderTarget, Texture, TextureOptions};
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\OS\{InputActionMap, Key};

use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\Render\QuickstartDebugMetricsOverlay;

class Application extends QuickstartApp
{
    private CPU $chip8;
    private Monitor $monitor;
    private Texture $monitorTexture;

    private bool $isRunning = false;

    private MonitorRenderer $monitorRenderer;

    /**
     * A function that is invoked once the app is ready to run.
     * This happens exactly just before the game loop starts.
     * 
     * Here you can prepare your game state, register services, callbacks etc.
     */
    public function ready() : void
    {
        parent::ready();

        // You can bind actions to keys in VISU 
        // this way you can decouple your game logic from the actual key bindings
        // and provides a comfortable way to access input state
        $actions = new InputActionMap;
        $actions->bindButton('pause', Key::SPACE);
        $actions->bindButton('step', Key::S);
        $actions->bindButton('fullscreen', Key::F);

        $this->inputContext->registerAndActivate('main', $actions);

        // load the inconsolata font to display the current score
        if ($this->vg->createFont('inconsolata', VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inconsolata/Inconsolata-Regular.ttf') === -1) {
            throw new Error('Inconsolata font could not be loaded.');
        }

        // create the chip8
        $this->monitor = new Monitor;
        $this->chip8 = new CPU(new Memory, $this->monitor);
        

        // // randomly fill the monitor
        // for ($i = 0; $i < 64; $i++) {
        //     for ($j = 0; $j < 32; $j++) {
        //         $this->monitor->setPixel($i, $j, rand(0, 255));
        //     }
        // }
        
        // // create a simple program
        // $this->chip8->memory->storeOpcode(0x0, Program::opJump(200));
        // $this->chip8->memory->storeOpcode(200, Program::opRandom(0));
        // $this->chip8->memory->storeOpcode(202, Program::opRandom(1));
        // $this->chip8->memory->storeOpcode(204, Program::opRandom(2));
        // $this->chip8->memory->storeOpcode(206, Program::opDrawPixel(0, 1, 2));
        // $this->chip8->memory->storeOpcode(208, Program::opJump(0));

        $args = $this->container->getParameter('argv');
        $romFile = $args[0] ?? null;
        if (!$romFile) {
            die('Please provide a ROM file as the first argument.');
        }

        $romFile = realpath($romFile);
        if (!file_exists($romFile)) {
            die('The provided ROM file does not exist.');
        }

        $this->chip8->loadRomFile($romFile);

        // create a texture for the monitor
        $this->monitorTexture = new Texture($this->gl, 'chip8_monitor');

        // create a renderer for the monitor
        $this->monitorRenderer = new MonitorRenderer($this->gl);
    }

    /**
     * Prepare / setup additional render passes before the quickstart draw pass 
     * This is an "setup" method meaning you should not emit any draw calls here, but 
     * rather add additional render passes to the pipeline.
     * 
     * @param RenderContext $context
     * @param RenderTargetResource $renderTarget
     * @return void 
     */
    public function setupDrawBefore(RenderContext $context, RenderTargetResource $renderTarget) : void
    {
        // upload the buffer
        $options = new TextureOptions();
        $options->dataFormat = GL_RED_INTEGER;
        $options->internalFormat = GL_R8UI;
        $options->dataType = GL_UNSIGNED_BYTE;
        $options->width = $this->monitor->width;
        $options->height = $this->monitor->height;
        $options->minFilter = GL_NEAREST;
        $options->magFilter = GL_NEAREST;
        $options->generateMipmaps = false;
        $this->monitorTexture->uploadBuffer($options, $this->monitor->blob);

        $textureResource = $context->pipeline->importTexture('chip8_monitor', $this->monitorTexture);

        $this->monitorRenderer->attachPass($context->pipeline, $renderTarget, $textureResource);
    }

    /**
     * Draw the scene. (You most definetly want to use this)
     * 
     * This is called from within the Quickstart render pass where the pipeline is already
     * prepared, a VG frame is also already started.
     */
    public function draw(RenderContext $context, RenderTarget $renderTarget) : void
    {
        // clear the screen
        // $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);

        QuickstartDebugMetricsOverlay::debugString('Current opcode: ' . dechex($this->chip8->peekOpcode()));


        // draw the current opcode
        // $this->vg->fontFace('inconsolata');
        // $this->vg->fontSize(16);
        // $this->vg->fillColor(VGColor::white());

        // $this->vg->text(10, 10, 'Current opcode: ' . dechex($this->chip8->peekOpcode()));

    }

    /**
     * Update the games state
     * This method might be called multiple times per frame, or not at all if
     * the frame rate is very high.
     * 
     * The update method should step the game forward in time, this is the place
     * where you would update the position of your game objects, check for collisions
     * and so on. 
     */
    public function update() : void
    {
        parent::update();

        // update key states
        if ($this->inputContext->actions->didButtonPress('pause')) {
            $this->isRunning = !$this->isRunning;
        }

        // step the CPU
        if ($this->inputContext->actions->didButtonPress('step')) {
            $this->chip8->runCycles(1);
        }

        // toggle fullscreen
        if ($this->inputContext->actions->didButtonPress('fullscreen')) {
            $this->monitorRenderer->fullscreen = !$this->monitorRenderer->fullscreen;
        }

        if (!$this->isRunning) {
            return;
        }

        $this->chip8->updateTimers();
        $this->chip8->runCycles(1000);
    }
}