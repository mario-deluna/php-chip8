<?php 

namespace App;

use App\Renderer\GuiRenderer;
use App\Renderer\MonitorRenderer;
use App\Renderer\RenderState;
use Error;
use GL\VectorGraphics\{VGAlign, VGColor, VGContext};

use VISU\Graphics\{RenderTarget, Texture, TextureOptions, Viewport};
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\OS\{Input, InputActionMap, Key};

use VISU\Quickstart\QuickstartApp;
use VISU\Quickstart\Render\QuickstartDebugMetricsOverlay;
use VISU\Signals\Input\DropSignal;

class Application extends QuickstartApp
{
    private CPU $chip8;
    private Monitor $monitor;
    private Texture $monitorTexture;

    private bool $isRunning = false;

    private RenderState $renderState;

    private MonitorRenderer $monitorRenderer;

    private GuiRenderer $guiRenderer;

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
        $actions->bindButton('toggle_crt', Key::T);
        $actions->bindButton('toggle_ghosting', Key::G);

        $this->inputContext->registerAndActivate('main', $actions);

        // load the VT323 font
        if ($this->vg->createFont('vt323', VISU_PATH_RESOURCES . '/font/VT323-Regular.ttf') === -1) {
            throw new Error('vt323 font could not be loaded.');
        }

        if ($this->vg->createFont('bebas', VISU_PATH_RESOURCES . '/font/BebasNeue-Regular.ttf') === -1) {
            throw new Error('Bebas font could not be loaded.');
        }

        // create the chip8
        $this->monitor = new Monitor;
        $this->chip8 = new CPU(new Memory, $this->monitor);

        $this->chip8->loadDefaultFont();

        $args = $this->container->getParameter('argv');
        if ($romFile = $args[0] ?? null) {
            $romFile = realpath($romFile);

            if (!file_exists($romFile)) {
                die(sprintf('Rom "%s" does not exist and could not be loaded.', $romFile));
            }

            $this->chip8->loadRomFile($romFile);
        }

        // register a file drop callback
        $this->dispatcher->register(Input::EVENT_DROP, function(DropSignal $signal) {
            $firstFile = $signal->paths[0] ?? null;
            $this->chip8->reset();
            $this->chip8->loadRomFile($firstFile);
            $this->isRunning = true;
        });

        // create a texture for the monitor
        $this->monitorTexture = new Texture($this->gl, 'chip8_monitor');
        
        // rendering state
        $this->renderState = new RenderState;

        // create a renderer for the monitor
        $this->monitorRenderer = new MonitorRenderer($this->gl, $this->renderState);

        // create a renderer for the GUI
        $this->guiRenderer = new GuiRenderer($this->vg, $this->renderState, $this->input);
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
        // we construct a viewport that matches the render target
        // this is not what we would usally use this helper for but in this case
        // its just a nice helper to work with our screen real estate
        $this->renderState->viewport = new Viewport(
            0, 
            $renderTarget->width / $renderTarget->contentScaleX,
            $renderTarget->height / $renderTarget->contentScaleY,
            0, 
            1.0, 
            1.0

        );

        $this->renderState->cpuIsRunning = $this->isRunning;

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
        QuickstartDebugMetricsOverlay::debugString('Current opcode: ' . dechex($this->chip8->peekOpcode()));

        $currentInst = $this->chip8->programCounter;

        // draw the screen frame
        $this->guiRenderer->renderGUI($this->chip8);

        // draw the current opcode
        $this->vg->fontFace('vt323');
        $this->vg->fontSize(16);
        $this->vg->fillColor(VGColor::white());
        $this->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);

        $range = 20;
        $ystart = 50;
        // dissassemble the next 20 instructions and last 20 instructions
        for ($i = $currentInst - $range; $i < $currentInst + $range; $i += 2) {
            if ($i < 0) {
                continue;
            }

            if ($i == $currentInst) {
                $this->vg->fillColor(VGColor::red());
            } else {
                $this->vg->fillColor(VGColor::white());
            }

            if ($string = $this->chip8->disassembleInstructionAt($i)) {
                $offset = $i - $currentInst + 20;
                $this->vg->text(10, $ystart + $offset * 10, $string);
            }
        }
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
            $this->renderState->fullscreenMonitor = !$this->renderState->fullscreenMonitor;
        }

        // toggle crt effect
        if ($this->inputContext->actions->didButtonPress('toggle_crt')) {
            $this->monitorRenderer->crtEffect = !$this->monitorRenderer->crtEffect;
        }

        // toggle ghosting
        if ($this->inputContext->actions->didButtonPress('toggle_ghosting')) {
            $this->monitorRenderer->ghostingEffect = !$this->monitorRenderer->ghostingEffect;
        }

        // update the keyboard states
        if ($this->chip8->wantKeyboardUpdates) {
            $this->chip8->keyPressStates[0x1] = (int) $this->input->isKeyPressed(Key::NUM_1);
            $this->chip8->keyPressStates[0x2] = (int) $this->input->isKeyPressed(Key::NUM_2);
            $this->chip8->keyPressStates[0x3] = (int) $this->input->isKeyPressed(Key::NUM_3);
            $this->chip8->keyPressStates[0xC] = (int) $this->input->isKeyPressed(Key::NUM_4);
            $this->chip8->keyPressStates[0x4] = (int) $this->input->isKeyPressed(Key::Q);
            $this->chip8->keyPressStates[0x5] = (int) $this->input->isKeyPressed(Key::W);
            $this->chip8->keyPressStates[0x6] = (int) $this->input->isKeyPressed(Key::E);
            $this->chip8->keyPressStates[0xD] = (int) $this->input->isKeyPressed(Key::R);
            $this->chip8->keyPressStates[0x7] = (int) $this->input->isKeyPressed(Key::A);
            $this->chip8->keyPressStates[0x8] = (int) $this->input->isKeyPressed(Key::S);
            $this->chip8->keyPressStates[0x9] = (int) $this->input->isKeyPressed(Key::D);
            $this->chip8->keyPressStates[0xE] = (int) $this->input->isKeyPressed(Key::F);
            $this->chip8->keyPressStates[0xA] = (int) $this->input->isKeyPressed(Key::Y);
            $this->chip8->keyPressStates[0x0] = (int) $this->input->isKeyPressed(Key::X);
            $this->chip8->keyPressStates[0xB] = (int) $this->input->isKeyPressed(Key::C);
            $this->chip8->keyPressStates[0xF] = (int) $this->input->isKeyPressed(Key::V);
        }

        if (!$this->isRunning) {
            return;
        }

        $this->chip8->updateTimers();
        if ($this->chip8->runCycles(8) === 0) {
            $this->isRunning = false;
        }
    }
}