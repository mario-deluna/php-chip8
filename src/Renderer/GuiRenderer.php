<?php

namespace App\Renderer;

use App\CPU;
use GL\Buffer\UByteBuffer;
use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use GL\VectorGraphics\VGContext;
use GL\VectorGraphics\VGPaint;
use VISU\OS\Input;
use VISU\OS\MouseButton;
use VISU\Signal\Dispatcher;
use VISU\Signal\Signal;

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

    public VGColor $displayColorStart;
    public VGColor $displayColorEnd;
    public VGColor $displayColorBorderer;
    public VGColor $displayColorText;

    public VGColor $valuePanelColorStart;
    public VGColor $valuePanelColorEnd;

    private array $lastButtonPress = [];
    private array $knobStaticPos = [];

    public function __construct(
        private VGContext $vg,
        private RenderState $renderState,
        private Input $input,
        private Dispatcher $dispatcher
    )
    {
        $this->bodyColorLight = new VGColor(0.776, 0.639, 0.541, 1.0);
        $this->bodyColorDark = new VGColor(0.565, 0.451, 0.369, 1.0);
        $this->panelColor = new VGColor(0.675, 0.549, 0.459, 1.0);

        $this->displayColorStart = new VGColor(0.016, 0.286, 0.271, 1.0);
        $this->displayColorEnd = new VGColor(0.027, 0.384, 0.353, 1.0);
        $this->displayColorBorderer = new VGColor(0.027, 0.173, 0.161, 1.0);
        $this->displayColorText = new VGColor(0.576, 1.0, 0.706, 1.0);

        $this->valuePanelColorStart = new VGColor(0.31, 0.31, 0.31, 1.0);
        $this->valuePanelColorEnd = new VGColor(0.314, 0.29, 0.259, 1.0);
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
        $nextPanel = $this->renderStatePanel($underMonitor->x - $this->panelPadding, $cpu);
        $this->renderDissaemblyPanel($nextPanel, $underMonitor->x - $this->panelPadding, $cpu);
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
        $this->vg->fontFace('vt323');
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

        // render a fullscreen button on the right
        if ($this->renderRoundButton(
            new Vec2($pos->x + $size->x - 20, $pos->y),
            20,
            $this->renderState->fullscreenMonitor ? '@@fullscreen' : '@@fullscreen',
            false,
            false,
            'icons',
            20
        )) {
            $this->renderState->fullscreenMonitor = !$this->renderState->fullscreenMonitor;
        }

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
    public function renderRoundButton(
        Vec2 $pos, 
        float $radius, 
        string $text, 
        bool $isActive = false, 
        bool $continues = false, 
        string $font = 'bebas',
        int $fontSize = 32
    ) : bool 
    {
        $mousePos = $this->input->getCursorPosition();
        $isHovering = $mousePos->distanceTo($pos) < $radius;
        $didPress = $this->input->hasMouseButtonBeenPressedThisFrame(MouseButton::LEFT);
        $isPressed = $this->input->isMouseButtonPressed(MouseButton::LEFT);

        $id = $text;
        // if the text contains a @@ we use the second part as the id
        if (strpos($text, '@@') !== false) {
            $parts = explode('@@', $text);
            $id = $parts[1];
            $text = $parts[0];
        }

        $buttonId = ((string) $pos->x . (string) $pos->y) . $id;

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
            $this->lastButtonPress[$buttonId] = glfwGetTime();
        }
        $this->vg->fill();

        // we fade out the highlight after a while
        $hoveringTime = 2.0;
        if (isset($this->lastButtonPress[$buttonId]) && glfwGetTime() - $this->lastButtonPress[$buttonId] < $hoveringTime) {

            $fadeDelta = $this->lastButtonPress[$buttonId] - glfwGetTime() + $hoveringTime;
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

        $this->vg->fontFace($font);
        $this->vg->fontSize($fontSize);
        $this->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $this->vg->fillColor(VGColor::white());

        // handle font specific offests
        if ($font === 'bebas') {
            $pos = $pos + new Vec2(0, 3);
        }
        elseif ($font === 'icons') {
            $pos = $pos + new Vec2(0, 0);
        }

        $this->vg->text($pos->x, $pos->y, $text);

        if (!$isHovering) return false;

        if ($continues) {
            return $isPressed;
        }
        return $didPress;
    }

    public function renderSelectorSwitch(Vec2 $pos, Vec2 $size, &$value, array $labels)
    {
        $selectorId = 'sel'.((string) $pos->x . (string) $pos->y);

        $r = $size->y * 0.5;

        $this->vg->beginPath();
        $this->vg->fillColor(new VGColor(0, 0, 0, 0.3));
        $this->vg->roundedRect($pos->x, $pos->y, $size->x, $size->y, $r);
        
        $gradient = $this->getIndetGradient($pos->y, $pos->y + $size->y);
        $this->vg->strokePaint($gradient);
        $this->vg->fill();
        $this->vg->strokeWidth(2);
        $this->vg->stroke();

        // get the center points
        $settings = count($labels);

        $w = ($size->x - $r * 2);
        $spacing = $w / ($settings - 1);

        $centerPoints = [];
        for ($i = 0; $i < $settings; $i++) {
            $centerPoints[] = $pos->x + $r + $spacing * $i;
        }


        // render a line from first to last
        $innerGuideColor = new VGColor(0.2, 0.2, 0.2, 1.0);
        $this->vg->beginPath();
        $this->vg->moveTo($pos->x + $r, $pos->y + $size->y * 0.5);
        $this->vg->lineTo($pos->x + $size->x - $r, $pos->y + $size->y * 0.5);
        $this->vg->strokeColor($innerGuideColor);
        $this->vg->strokeWidth(5);
        $this->vg->stroke();

        // render a circle for each setting
        $this->vg->fillColor($innerGuideColor);
        for ($i = 0; $i < $settings; $i++) {
            $p = $centerPoints[$i];
            $this->vg->beginPath();
            $this->vg->circle($p, $pos->y + $size->y * 0.5, $r * 0.25);
            $this->vg->fill();

            // handle selection
            if ($this->input->hasMouseButtonBeenPressedThisFrame(MouseButton::LEFT)) {
                if ($this->input->getCursorPosition()->distanceTo(new Vec2($p, $pos->y + $size->y * 0.5)) < $r) {
                    $value = array_keys($labels)[$i];
                }
            }
        }

        // render a label on top
        $this->vg->fontFace('bebas');
        $this->vg->fontSize(16);
        $this->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);

        foreach ($labels as $label) {
            $i = array_search($label, $labels);
            $p = $centerPoints[$i];
            
            // white if its the current value
            $this->vg->fillColor($value === $i ? VGColor::white() : VGColor::white()->darken(0.15));
            $this->vg->text($p, $pos->y - 20, $label);
        }

        // render a knob for the current value
        $knobAtIndex = array_search($value, array_keys($labels));
        $knobPos = $centerPoints[$knobAtIndex];

        if (!isset($this->knobStaticPos[$selectorId])) {
            $this->knobStaticPos[$selectorId] = $knobPos;
        }

        // move the knob closer to the target
        $this->knobStaticPos[$selectorId] = $this->knobStaticPos[$selectorId] + ($knobPos - $this->knobStaticPos[$selectorId]) * 0.1;
        $realKnobPos = $this->knobStaticPos[$selectorId];

        // knob color
        $knobColorOuterStart = new VGColor(0.918, 0.933, 0.937, 1.0);
        $knobColorOuterEnd = new VGColor(0.459, 0.475, 0.478, 1.0);
        $knobColorInnerStart = new VGColor(0.514, 0.553, 0.549, 1.0);
        $knobColorInnerEnd = new VGColor(0.71, 0.725, 0.729, 1.0);

        $this->vg->beginPath();
        $this->vg->circle($realKnobPos, $pos->y + $size->y * 0.5, $r * 0.6);
        $knobFillPaint = $this->vg->linearGradient($realKnobPos - $r, $pos->y, $realKnobPos + $r, $pos->y + $size->y, $knobColorInnerStart, $knobColorInnerEnd);
        $this->vg->fillPaint($knobFillPaint);
        $this->vg->fill();
        
        $knobStrokePaint = $this->vg->linearGradient($realKnobPos - $r, $pos->y, $realKnobPos + $r, $pos->y + $size->y, $knobColorOuterStart, $knobColorOuterEnd);
        $this->vg->strokePaint($knobStrokePaint);
        $this->vg->strokeWidth(8);
        $this->vg->stroke();
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
        $buttonHalfSpace = min($buttonHalfSpace, ($innerSize->x * 0.4 / 4) * 0.5);
        $buttonHalfSpace = max($buttonHalfSpace, 20);
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

        $rightSidePos = $innerPos + new Vec2($innerSize->x * 0.5, 0);
        $rightSideSize = $innerSize * new Vec2(0.5, 1);

        // render a persitance of vision selector switch
        $labelPos = $rightSidePos + new Vec2(20, 68);
        $this->vg->fontFace('bebas');
        $this->vg->fontSize(16);
        $this->vg->textAlign(VGAlign::RIGHT | VGAlign::MIDDLE);
        $this->vg->fillColor(VGColor::white());
        $this->vg->text($labelPos->x, $labelPos->y, 'Ghosting');

        $currentGhostingValue = $this->renderState->ghostingEffectLevel->value;
        $this->renderSelectorSwitch($rightSidePos + new Vec2(20 + 20, 50), new Vec2($rightSideSize->x - 60, 35), $currentGhostingValue, [
            GhostingEffectLevel::None->value => 'None',
            GhostingEffectLevel::Low->value => 'Low',
            GhostingEffectLevel::Medium->value => 'Medium',
            GhostingEffectLevel::High->value => 'High',
        ]);

        $this->renderState->ghostingEffectLevel = GhostingEffectLevel::from($currentGhostingValue);

        // crt effect toggle
        $labelPos = $rightSidePos + new Vec2(20, 168);
        $this->vg->fontFace('bebas');
        $this->vg->fontSize(16);
        $this->vg->textAlign(VGAlign::RIGHT | VGAlign::MIDDLE);
        $this->vg->fillColor(VGColor::white());
        $this->vg->text($labelPos->x, $labelPos->y, 'CRT Effect');

        $this->renderSelectorSwitch($rightSidePos + new Vec2(20 + 20, 150), new Vec2($rightSideSize->x - 60, 35), $this->renderState->crtEffectEnabled, [
            false => 'Off',
            true => 'On',
        ]);

        if ($this->renderState->fullscreenMonitor) {
            if ($this->renderRoundButton($innerPos + new Vec2(30, 30), 30, "@@pause", false, false, 'icons', '20')) {
                $this->dispatcher->dispatch('cpu.pause', new Signal);
            }
        } else {
            if ($this->renderRoundButton($innerPos + new Vec2(30, 30), 30, "@@pause", false, false, 'icons', '20')) {
                $this->dispatcher->dispatch('cpu.start', new Signal);
            }
        }
    }

    public function renderTinyDisplay(Vec2 $pos, Vec2 $size, string $text)
    {
        $gradiend = $this->vg->linearGradient(0, $pos->y, 0, $pos->y + $size->y, $this->displayColorStart, $this->displayColorEnd);

        $this->vg->beginPath();
        $this->vg->roundedRect($pos->x, $pos->y, $size->x, $size->y, 6);
        $this->vg->fillPaint($gradiend);
        $this->vg->strokeColor($this->displayColorBorderer);
        $this->vg->fill();
        $this->vg->strokeWidth(2);
        $this->vg->stroke();

        $this->vg->fontFace('vt323');
        
        $textSize = 40;
        // note this is really slow obviously
        // but im lazy and it will do for now as we only have really on 
        // large display to render
        if (strlen($text) > 4) {
            do {
                $textSize -= 2;
                $this->vg->fontSize($textSize);
                $bounds = new Vec4();
                $w = $this->vg->textBounds($pos->x, $pos->y, $text, $bounds);
            } while ($w > $size->x);
        } else {
            $this->vg->fontSize(40);
        }

        $this->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);


        // render a glow
        $this->vg->fontBlur(10);
        $this->vg->fillColor($this->displayColorText);
        $this->vg->text($pos->x + 10, $pos->y + $size->y * 0.5, $text);

        // render the actual text   
        $this->vg->fontBlur(0);
        $this->vg->fillColor($this->displayColorText);
        $this->vg->text($pos->x + 10, $pos->y + $size->y * 0.5, $text);        
    }

    public function renderValuePanel(Vec2 $pos, Vec2 $size)
    {
        $this->vg->beginPath();
        $paint = $this->vg->linearGradient(0, $pos->y, 0, $pos->y + $size->y, $this->valuePanelColorStart, $this->valuePanelColorEnd);
        $this->vg->fillPaint($paint);
        $this->vg->roundedRect($pos->x, $pos->y, $size->x, $size->y, 8);
        $this->vg->fill();
    }

    public function renderValueDisplay(Vec2 $pos, Vec2 $size, string $value, string $label)
    {
        $this->renderValuePanel($pos, $size);

        $padding = 8;
        $height = $size->y * 0.5 - $padding;

        $this->vg->fontFace('bebas');
        $this->vg->fontSize(16);
        $this->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $this->vg->fillColor(new VGColor(0.647, 0.647, 0.647, 1.0));
        $this->vg->text(
            $pos->x + $size->x * 0.5,
            $pos->y - $padding + $height * 2,
            $label
        );

        // render display on top
        $dpos = $pos + new Vec2($padding, $padding);
        $dsize = new Vec2($size->x - $padding * 2, $height);

        $this->renderTinyDisplay($dpos, $dsize, $value);
    }

    /**
     * Register display looks very similar to the value one but the label is on the left
     */
    public function renderRegisterDisplay(Vec2 $pos, Vec2 $size, string $value, string $label)
    {
        $this->renderValuePanel($pos, $size);

        $padding = 8;
        $width = $size->x * 0.5 - $padding;

        $this->vg->fontFace('bebas');
        $this->vg->fontSize(28);
        $this->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $this->vg->fillColor(new VGColor(0.647, 0.647, 0.647, 1.0));
        $this->vg->text(
            $pos->x + $width * 0.5,
            $pos->y + $size->y * 0.5 + 2,
            $label
        );

        // render display on right
        $dpos = $pos + new Vec2($size->x * 0.5, $padding);
        $dsize = new Vec2($width, $size->y - $padding * 2);

        $this->renderTinyDisplay($dpos, $dsize, $value);
    }

    public function renderStatePanel(float $width, CPU $cpu) : float
    {
        $pos = new Vec2($this->panelPadding, $this->panelPadding);
        $panelSize = new Vec2($width - $pos->x, 382);

        $this->renderBodyPanel($pos, $panelSize, false, $this->panelRadius);

        // inside
        $innerPos = $pos + new Vec2($this->panelBorderWidth, $this->panelBorderWidth);
        $innerSize = $panelSize - new Vec2($this->panelBorderWidth * 2, $this->panelBorderWidth * 2);

        $this->vg->beginPath();
        $this->vg->fillColor($this->panelColor);    
        $this->vg->roundedRect($innerPos->x, $innerPos->y, $innerSize->x, $innerSize->y, $this->panelRadius * 0.8);
        $this->vg->fill();


        // we render 4 displays at the top for
        // - program counter
        // - stack pointer
        // - register I
        // - delay timer
        $gutter = 10;
        $x = $innerPos->x + $gutter;
        $displaySize = new Vec2(($innerSize->x - $gutter * 5) * 0.25, 100);
        $displayPos = $innerPos + new Vec2($gutter, $gutter);
        
        $this->renderValueDisplay($displayPos, $displaySize, str_pad(dechex($cpu->programCounter), 4, '0', STR_PAD_LEFT), 'Program Counter');
        $displayPos->x = $displayPos->x + $displaySize->x + $gutter;
        $this->renderValueDisplay($displayPos, $displaySize, str_pad(dechex($cpu->stackPointer), 2, '0', STR_PAD_LEFT), 'Stack Pointer');
        $displayPos->x = $displayPos->x + $displaySize->x + $gutter;
        $this->renderValueDisplay($displayPos, $displaySize, str_pad(dechex($cpu->registerI), 4, '0', STR_PAD_LEFT), 'Register I');
        $displayPos->x = $displayPos->x + $displaySize->x + $gutter;
        $this->renderValueDisplay($displayPos, $displaySize, str_pad(dechex($cpu->timers[0]), 2, '0', STR_PAD_LEFT), 'Delay Timer');

        $startY = $displayPos->y + $displaySize->y;
    
        // render the 16 registers in a 4x4 grid
        $gutter = 10;
        $x = $innerPos->x + $gutter;
        $displaySize = new Vec2(($innerSize->x - $gutter * 5) * 0.25, 50);
        $displayPos = $innerPos + new Vec2($gutter, $startY);

        for ($i = 0; $i < 16; $i++) {
            $this->renderRegisterDisplay($displayPos, $displaySize, strtoupper(str_pad(dechex($cpu->registers[$i]), 2, '0', STR_PAD_LEFT)), 'V' . dechex($i));
            $displayPos->x = $displayPos->x + $displaySize->x + $gutter;
            if ($i % 4 == 3) {
                $displayPos->x = $innerPos->x + $gutter;
                $displayPos->y = $displayPos->y + $displaySize->y + $gutter;
            }
        }

        return $pos->y + $panelSize->y + $this->panelPadding;
    }

    public function renderDissaemblyPanel(float $y, float $width, CPU $cpu) 
    {
        $panelPos = new Vec2($this->panelPadding, $y);
        $panelSize = new Vec2($width - $this->panelPadding, $this->renderState->viewport->height - $y - $this->panelPadding);

        $this->renderBodyPanel($panelPos, $panelSize, false, $this->panelRadius);

        // render display box
        $displayPos = new Vec2($panelPos->x + 10, $panelPos->y + 10);
        $displaySize = new Vec2($panelSize->x - 20, $panelSize->y - 20);

        $gradiend = $this->vg->linearGradient(0, $displayPos->y, 0, $displayPos->y + $displaySize->y, $this->displayColorStart, $this->displayColorEnd);

        $this->vg->beginPath();
        $this->vg->roundedRect($displayPos->x, $displayPos->y, $displaySize->x - $width * 0.5, $displaySize->y, 10);
        $this->vg->fillPaint($gradiend);
        $this->vg->strokeColor($this->displayColorBorderer);
        $this->vg->fill();
        $this->vg->strokeWidth(2);
        $this->vg->stroke();

        // draw the current opcode
        $this->vg->fontFace('vt323');
        $this->vg->fontSize(16);
        $this->vg->fillColor($this->displayColorText);
        $this->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);

        // estimate range based on height of the display
        $displayInnerSpace = $displaySize->y - 20;

        $range = (int) (($displayInnerSpace / 10) / 2);
        // $ystart = 50;
        // $currentInst = $cpu->programCounter;        

        // // dissassemble the next 20 instructions and last 20 instructions
        // for ($i = $currentInst - $range; $i < $currentInst + $range; $i += 2) {
        //     if ($i < 0) {
        //         continue;
        //     }

        //     if ($i == $currentInst) {
        //         $this->vg->fillColor(VGColor::red());
        //     } else {
        //         $this->vg->fillColor(VGColor::white());
        //     }

        //     if ($string = $cpu->disassembleInstructionAt($i)) {
        //         $offset = $i - $currentInst + 20;
        //         $this->vg->text(10, $ystart + $offset * 10, $string);
        //     }
        // }

        $instructionText = "";
        $currentInst = $cpu->programCounter;
        for ($i = $currentInst - $range; $i < $currentInst + $range; $i++) {
            if ($i < 0) {
                continue;
            }

            if (!$diss = $cpu->disassembleInstructionAt($i)) {
                continue;
            }

            $counterString = str_pad(dechex($i), 4, '0', STR_PAD_LEFT);

            $prefix = $i == $currentInst ? "=> " : "|  ";
            $instructionText .= $prefix . $counterString . ": " . $diss . "\n";
        }

        $this->vg->scissor($displayPos->x, $displayPos->y, $displaySize->x, $displaySize->y);
        $this->vg->textBox($displayPos->x, $displayPos->y, $displaySize->x, $instructionText);

        $this->vg->resetScissor();

        // render start pause reset buttons
        $buttonPanel = new Vec2($panelPos->x + $width * 0.5 - 10, $panelPos->y + 10);

        if ($this->renderState->cpuIsRunning) {
            if ($this->renderRoundButton($buttonPanel + new Vec2(30, 30), 30, "@@pause", false, false, 'icons', '20')) {
                $this->dispatcher->dispatch('cpu.pause', new Signal);
            }
        } else {
            if ($this->renderRoundButton($buttonPanel + new Vec2(30, 30), 30, "@@pause", false, false, 'icons', '20')) {
                $this->dispatcher->dispatch('cpu.start', new Signal);
            }
        }

        if ($this->renderRoundButton($buttonPanel + new Vec2(100, 30), 30, "@@step", false, false, 'icons', '20')) {
            $cpu->runCycles(1);
        }

        // reset button on the right
        if ($this->renderRoundButton($buttonPanel + new Vec2($width * 0.5 - 50, 30), 30, "@@reset", false, false, 'icons', '20')) {
            $cpu->reset();
            if ($cpu->currentRomFilePath) {
                $cpu->loadRomFile($cpu->currentRomFilePath);
            }
        }

        // render display with the current rom
        $this->renderValueDisplay(
            $buttonPanel + new Vec2(0, $displaySize->y - 100), 
            new Vec2($displaySize->x * 0.5, 100), 
            basename($cpu->currentRomFilePath), 
            'ROM'
        );

        // if ($this->renderState->cpuIsRunning) {
        //     // render a play icon
        //     $this->vg->translate($buttonPanel->x, $buttonPanel->y);
        //     $this->vg->beginPath();
        //     $this->vg->moveTo(-5, -8);
        //     $this->vg->lineTo(-5, 8);
        //     $this->vg->lineTo(8, 0);
        //     $this->vg->fillColor(VGColor::white());
        //     $this->vg->fill();
        // } else {
        //     // render a pause icon
        //     $this->vg->translate($buttonPanel->x, $buttonPanel->y);
        //     $this->vg->beginPath();
        //     $this->vg->rect(-5, -8, 5, 16);
        //     $this->vg->rect(0, -8, 5, 16);
        //     $this->vg->fillColor(VGColor::white());
        //     $this->vg->fill();
        // }
    }
}