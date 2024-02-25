<?php

namespace App\Renderer;

use App\CPU;
use GL\Buffer\UByteBuffer;
use GL\Math\Vec2;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use GL\VectorGraphics\VGContext;
use GL\VectorGraphics\VGPaint;
use VISU\OS\Input;
use VISU\OS\MouseButton;

class GuiRenderer
{
    public float $monitorRadius = 20.0;
    public float $monitorFrameWidth = 20.0;
    public float $monitorFrameFrontPanel = 60.0;

    public float $panelPadding = 15.0;
    public float $panelRadius = 20.0;
    public float $panelBorderWidth = 6.0;


    public VGColor $bodyColorLight;
    public VGColor $bodyColorDark;
    public VGColor $panelColor;

    public function __construct(
        private VGContext $vg,
        private RenderState $renderState,
        private Input $input
    )
    {
        $this->bodyColorLight = new VGColor(0.776, 0.639, 0.541, 1.0);
        $this->bodyColorDark = new VGColor(0.565, 0.451, 0.369, 1.0);
        $this->panelColor = new VGColor(0.675, 0.549, 0.459, 1.0);
    }

    /**
     * Creates a gradient paint that goes vertically from light to dark
     */
    public function createBodyGradient($ystart, $yend) : VGPaint
    {
        return $this->vg->linearGradient(0, $ystart, 0, $yend, $this->bodyColorLight, $this->bodyColorDark);
    }

    public function renderBodyPanel(Vec2 $pos, Vec2 $size, bool $invert = false, float $radius = 10.0, bool $autofill = true)
    {
        $this->vg->beginPath();

        if ($invert) {
            $paint = $this->createBodyGradient($pos->y + $size->y, $pos->y);
        } else {
            $paint = $this->createBodyGradient($pos->y, $pos->y + $size->y);
        }

        $this->vg->fillPaint($paint);
        $this->vg->roundedRect($pos->x, $pos->y, $size->x, $size->y, $radius);
        
        if ($autofill) {
            $this->vg->fill();
        }
    }

    public function renderGUI(CPU $cpu)
    {
        // No GUI in fullscreen mode
        if ($this->renderState->fullscreenMonitor) {
            return;
        }

        $underMonitor = $this->renderMonitorFrame($cpu);
        $this->renderKeyboard($underMonitor, $cpu);
    }

    /**
     * @return Vec2 The xy position of the monitor bottom left corner
     */
    public function renderMonitorFrame(CPU $cpu) : Vec2
    {
        $this->vg->beginPath();

        $pos = $this->renderState->getMonitorPosition();
        $size = $this->renderState->getMonitorSize();

        $framePos = $pos - new Vec2($this->monitorFrameWidth, $this->monitorFrameWidth);
        $frameSize = $size + new Vec2($this->monitorFrameWidth * 2, $this->monitorFrameWidth * 2);

        // make the outer panel bigger to show a front panel
        $frameSize->y = $frameSize->y + $this->monitorFrameFrontPanel;

        $framePosInner = $pos - new Vec2($this->monitorFrameWidth * 0.5, $this->monitorFrameWidth * 0.5);
        $frameSizeInner = $size + new Vec2($this->monitorFrameWidth, $this->monitorFrameWidth);
        
        $this->renderBodyPanel($framePos, $frameSize, false, $this->monitorRadius, false);

        // render a hole into the monitor where the render display data is
        $this->vg->roundedRect($pos->x, $pos->y, $size->x, $size->y, 10);
        $this->vg->pathWinding(VGContext::CW);
        $this->vg->fill();

        // and the inner one
        $this->vg->pathWinding(VGContext::CCW);
        $this->renderBodyPanel($framePosInner, $frameSizeInner, true, $this->monitorRadius, false);
        $this->vg->roundedRect($pos->x, $pos->y, $size->x, $size->y, 10);
        $this->vg->pathWinding(VGContext::CW);
        $this->vg->fill();


        // render front panel text
        $this->vg->fontFace('bebas');
        $this->vg->fontSize(32);
        $this->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);

        $frontPanelVBB = new Vec2(
            $pos->y + $size->y + $this->monitorFrameWidth * 0.5,
            $pos->y + $size->y + $this->monitorFrameWidth + $this->monitorFrameFrontPanel
        );

        $textY = $frontPanelVBB->x + ($frontPanelVBB->y - $frontPanelVBB->x) * 0.5;

        // now a white shadow for the emboss effect
        $this->vg->fontBlur(2);
        $this->vg->fillColor(new VGColor(1.0, 1.0, 1.0, 0.7));
        $this->vg->text($pos->x + $size->x * 0.5, $textY + 1, 'PHP CHIP-8');

        // render the label shadow
        $this->vg->fontBlur(2);
        $this->vg->fillColor(VGColor::black());
        $this->vg->text($pos->x + $size->x * 0.5, $textY - 1, 'PHP CHIP-8');

        // render the actual label
        $this->vg->fontBlur(0);
        $this->vg->fillColor(VGColor::white());
        $this->vg->text($pos->x + $size->x * 0.5, $textY, 'PHP CHIP-8');


        // render a power LED on the right when the CPU is running
        $ledPos = $pos;
        $ledPos->y = $frontPanelVBB->x + $this->monitorFrameFrontPanel * 0.5 + 5;

        $this->vg->beginPath();
        $this->vg->circle($ledPos->x, $ledPos->y, 5.0);
        $this->vg->strokeColor(VGColor::black());
        $this->vg->fillColor(!$this->renderState->cpuIsRunning ? new VGColor(0.8, 0.2, 0.2, 1.0) : new VGColor(0.2, 0.8, 0.2, 1.0));
        $this->vg->strokeWidth(2);
        $this->vg->fill();
        $this->vg->stroke();

        return new Vec2($framePos->x, $frontPanelVBB->y);
    }

    public function getIndetGradient($ystart, $yend) : VGPaint
    {
        return $this->vg->linearGradient(0, $ystart, 0, $yend, new VGColor(0.0, 0.0, 0.0, 0.4), new VGColor(1.0, 1.0, 1.0, 0.2));
    }

    /**
     * @param bool $continues If true the button will return true as long as the mouse is pressed
     * @return bool 
     */
    public function renderRoundButton(Vec2 $pos, float $radius, string $text, bool $isActive = false, bool $continues = false) : bool 
    {
        $mousePos = $this->input->getCursorPosition();
        $isHovering = $mousePos->distanceTo($pos) < $radius;
        $didPress = $this->input->hasMouseButtonBeenPressed(MouseButton::LEFT);
        $isPressed = $this->input->isMouseButtonPressed(MouseButton::LEFT);

        $buttonId = ((string) $pos) . $text;
        static $lastButtonPress = [];

        // render a the indent for the button
        $this->vg->beginPath();
        $this->vg->circle($pos->x, $pos->y, $radius);
        $this->vg->fillPaint($this->getIndetGradient($pos->y - $radius, $pos->y + $radius));
        $this->vg->fill();

        // now a dark circle to akt as a spacer
        $this->vg->beginPath();
        $this->vg->circle($pos->x, $pos->y, $radius * 0.9);
        $this->vg->fillColor(VGColor::black());
        if (($isHovering && ($isPressed || $didPress)) || $isActive) {
            $lastButtonPress[$buttonId] = glfwGetTime();
        }
        $this->vg->fill();

        // we fade out the highlight after a while
        $hoveringTime = 2.0;
        if (isset($lastButtonPress[$buttonId]) && glfwGetTime() - $lastButtonPress[$buttonId] < $hoveringTime) {

            $fadeDelta = $lastButtonPress[$buttonId] - glfwGetTime() + $hoveringTime;
            $fadeDelta /= $hoveringTime;

            $this->vg->beginPath();
            $this->vg->circle($pos->x, $pos->y, $radius * 0.9);
            $this->vg->fillColor(new VGColor(0.973, 0.875, 0.012, $fadeDelta));
            $this->vg->fill();
        }

        // btn colors
        $buttonInnerA = new VGColor(0.263, 0.149, 0.027, 1.0);
        $buttonInnerB = new VGColor(0.396, 0.22, 0.02, 1.0);
        $buttonOuterA = new VGColor(0.655, 0.369, 0.063, 1.0);
        $buttonOuterB = new VGColor(0.263, 0.149, 0.027, 1.0);

        if ($isHovering) {
            $buttonInnerA = $buttonInnerA->darken(0.3);
        }

        // render the button
        $innerGradient = $this->vg->linearGradient(0, $pos->y - $radius, 0, $pos->y + $radius, $buttonInnerA, $buttonInnerB);
        $outerGradient = $this->vg->linearGradient(0, $pos->y - $radius, 0, $pos->y + $radius, $buttonOuterA, $buttonOuterB);

        $this->vg->beginPath();
        $this->vg->circle($pos->x, $pos->y, $radius * 0.8);
        $this->vg->fillPaint($innerGradient);
        $this->vg->strokePaint($outerGradient);
        $this->vg->fill();
        $this->vg->strokeWidth(2);
        $this->vg->stroke();

        $this->vg->fontFace('bebas');
        $this->vg->fontSize(32);
        $this->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $this->vg->fillColor(VGColor::white());
        $this->vg->text($pos->x, $pos->y + 3, $text);

        if (!$isHovering) return false;

        if ($continues) {
            return $isPressed;
        }
        return $didPress;
    }

    public function renderKeyboard(Vec2 $startPos, CPU $cpu)
    {
        $startPos->y = $startPos->y + $this->panelPadding;

        $panelSize = new Vec2(
            $this->renderState->viewport->width - $startPos->x - $this->panelPadding, 
            $this->renderState->viewport->height - $startPos->y - $this->panelPadding
        );

        $this->renderBodyPanel($startPos, $panelSize, false, $this->panelRadius);

        $innerPos = $startPos + new Vec2($this->panelBorderWidth, $this->panelBorderWidth);
        $innerSize = $panelSize - new Vec2($this->panelBorderWidth * 2, $this->panelBorderWidth * 2);

        // render the panel inside
        $this->vg->beginPath();
        $this->vg->fillColor($this->panelColor);
        $this->vg->roundedRect($innerPos->x, $innerPos->y, $innerSize->x, $innerSize->y, $this->panelRadius * 0.8);
        $this->vg->fill();

        // render the keyboard buttons
        $buttons = [
            ['1', '2', '3', 'C'],
            ['4', '5', '6', 'D'],
            ['7', '8', '9', 'E'],
            ['A', '0', 'B', 'F'],
        ];

        $buttonHalfSpace = ($innerSize->y / 4) * 0.5;
        $cpu->wantKeyboardUpdates = true;

        for ($y = 0; $y < 4; $y++) {
            for ($x = 0; $x < 4; $x++) {
                $buttonX = $innerPos->x + $x * $buttonHalfSpace * 2 + $buttonHalfSpace;
                $buttonY = $innerPos->y + $y * $buttonHalfSpace * 2 + $buttonHalfSpace;

                // convert the hex string to a number
                $n = hexdec($buttons[$y][$x]);

                $isDown = $this->renderRoundButton(
                    new Vec2($buttonX, $buttonY),
                    $buttonHalfSpace * 0.85,
                    $buttons[$y][$x],
                    $cpu->keyPressStates[$n] > 0,
                    true
                );

                // stop keyboard updates if the button is pressed
                if ($isDown) {
                    $cpu->wantKeyboardUpdates = false;
                }

                $cpu->keyPressStates[$n] = $isDown ? 1 : 0;
            }
        }
    }
}