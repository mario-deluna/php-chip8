<?php 

namespace App;

use Exception;
use GL\Buffer\UByteBuffer;
use GL\Buffer\UShortBuffer;

/**
 * This class simulates the Chip8 CPU, with all its registers and operations
 */
class CPU
{
    private UByteBuffer $registersV;

    private int $registerI = 0x0;

    private int $registerVF = 0x0;

    private UByteBuffer $timers;

    private int $programCounter = 0x200;

    private int $stackPointer = 0x0;

    private UShortBuffer $stack;

    private UByteBuffer $keyPressStates;


    public function __construct(
        public Memory $memory,
        public Monitor $monitor
    )
    {
        $this->registersV = new UByteBuffer();
        $this->registersV->fill(16, 0x0); 

        $this->timers = new UByteBuffer();
        $this->timers->fill(2, 0x0);

        $this->stack = new UShortBuffer();
        $this->stack->fill(64, 0x0);

        $this->keyPressStates = new UByteBuffer();
        $this->keyPressStates->fill(16, 0x0);
    }

    public function loadRomFile(string $filename)
    {
        $data = file_get_contents($filename);

        // convert the data to a byte array
        $bytes = array_values(unpack('C*', $data));

        // store the data in the memory
        for($i = 0; $i < count($bytes); $i++) {
            $this->memory->blob[$i + 0x200] = $bytes[$i];
        }

    }

    /**
     * Returns the current opcode from the memory and increments to the next one
     * 
     * I've taken these directly from: http://devernay.free.fr/hacks/chip8/C8TECH10.HTM#00E0
     * 
     * nnn or addr - A 12-bit value, the lowest 12 bits of the instruction
     * n or nibble - A 4-bit value, the lowest 4 bits of the instruction
     * x - A 4-bit value, the lower 4 bits of the high byte of the instruction
     * y - A 4-bit value, the upper 4 bits of the low byte of the instruction
     * kk or byte - An 8-bit value, the lowest 8 bits of the instruction
     * 
     * Unfortunately, PHP does not have macros that would allow me to easily extract these values from the opcode
     * I do not want to make them separate functions calls as the overhead is significant...
     * 
     * Quick list of how to extract the values for my own reference:
     *   nnn = $opcode & 0x0FFF
     *   n = $opcode & 0x000F
     *   x = $opcode >> 8 & 0x0F
     *   y = $opcode >> 4 & 0x0F
     *   kk = $opcode & 0x00FF
     * 
     */
    private function fetchOpcode(): int
    {
        $opcode = $this->memory->blob[$this->programCounter] << 8 | $this->memory->blob[$this->programCounter + 1];
        $this->programCounter += 2;
        return $opcode;
    }

    /**
     * Returns the current opcode from the memory WITHOUT incrementing to the next one
     */
    public function peekOpcode(): int
    {
        return $this->memory->blob[$this->programCounter] << 8 | $this->memory->blob[$this->programCounter + 1];
    }

    /**
     * Runs the CPU until the program ends
     */
    public function run()
    {
        while (true) {
            $opcode = $this->fetchOpcode();
            echo sprintf('exec: 0x%X' . PHP_EOL, $opcode);
            $this->executeOpcode($opcode);
            sleep(1);
        }
    }

    /**
     * Runs the CPU for a approximate given amount of time and then returns.
     * 
     * @param float $deltaTimeInMs 
     * @return int the exit code of the program, -1 if the program is still running
     */
    public function runFor(float $deltaTimeInMs) : int
    {
        $startTime = microtime(true);
        $endTime = $startTime + $deltaTimeInMs / 1000.0;

        while (microtime(true) < $endTime) {
            $opcode = $this->fetchOpcode();
            $this->executeOpcode($opcode);
        }

        return -1;
    }

    /**
     * Runs the CPU for a given amount of cycles and then returns.
     * 
     * @param int $count the amount of cycles to run
     * @return int the exit code of the program, -1 if the program is still running
     */
    public function runCycles(int $count) : int
    {
        for ($i = 0; $i < $count; $i++) {
            $opcode = $this->fetchOpcode();
            $this->executeOpcode($opcode);
        }

        return -1;
    }

    /**
     * Time udpate, decrements the timers. Call this at 60Hz
     */
    public function updateTimers()
    {
        if ($this->timers[0] > 0) {
            $this->timers[0] = $this->timers[0] - 1;
        }

        if ($this->timers[1] > 0) {
            $this->timers[1] = $this->timers[1] - 1;
        }
    }

    private function opNotImplemented(int $opcode)
    {
        // throw new \Exception(sprintf('Opcode 0x%X not implemented', $opcode));
    }

    private function opDrawPixel(int $opcode)
    {
        $x = $this->registersV[$opcode >> 8 & 0x0F];
        $y = $this->registersV[$opcode >> 4 & 0x0F];
        $color = $this->registersV[$opcode & 0x0F];

        $this->monitor->setPixel($x, $y, $color);
    }
    
    private function opRandom(int $opcode)
    {
        $this->registersV[$opcode >> 8 & 0x0F] = rand(0, 31);
    }

    /**
     * 1nnn - JP addr
     */
    private function opJump(int $opcode)
    {
        $address = $opcode & 0x0FFF;
        $this->programCounter = $address;
    }

    /**
     * 2nnn - CALL addr
     */
    private function opCallSubroutine(int $opcode)
    {
        $address = $opcode & 0x0FFF;
        $this->stack[$this->stackPointer++] = $this->programCounter;
        $this->programCounter = $address;
    }

    /**
     * 3xkk - SE Vx, byte
     */
    private function opSkipIfEqual(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $value = $opcode & 0x00FF;
        
        if ($this->registersV[$register] === $value) {
            $this->programCounter += 2;
        }
    }

    /**
     * 4xkk - SNE Vx, byte
     */
    private function opSkipIfNotEqual(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $value = $opcode & 0x00FF;
        
        if ($this->registersV[$register] !== $value) {
            $this->programCounter += 2;
        }
    }

    /**
     * 5xy0 - SE Vx, Vy
     */
    private function opSkipIfEqualRegisters(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        
        if ($this->registersV[$registerX] === $this->registersV[$registerY]) {
            $this->programCounter += 2;
        }
    }

    /**
     * 6xkk - LD Vx, byte
     */
    private function opLoadValue(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $value = $opcode & 0x00FF;
        $this->registersV[$register] = $value;
    }

    /**
     * 7xkk - ADD Vx, byte
     */
    private function opAddValue(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $value = $opcode & 0x00FF;
        $this->registersV[$register] += $value;
    }

    /**
     * 8xy0 - LD Vx, Vy
     */
    private function opLoadRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $this->registersV[$registerX] = $this->registersV[$registerY];
    }

    /**
     * 8xy1 - OR Vx, Vy
     */
    private function opOrRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $this->registersV[$registerX] |= $this->registersV[$registerY];
    }

    /**
     * 8xy2 - AND Vx, Vy
     */
    private function opAndRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $this->registersV[$registerX] &= $this->registersV[$registerY];
    }

    /**
     * 8xy3 - XOR Vx, Vy
     */
    private function opXorRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $this->registersV[$registerX] ^= $this->registersV[$registerY];
    }

    /**
     * 8xy4 - ADD Vx, Vy
     */
    private function opAddRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $result = $this->registersV[$registerX] + $this->registersV[$registerY];
        $this->registersV[$registerX] = $result & 0xFF;
        $this->registerVF = $result > 0xFF ? 1 : 0;
    }

    /**
     * 8xy5 - SUB Vx, Vy
     */
    private function opSubRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $result = $this->registersV[$registerX] - $this->registersV[$registerY];
        $this->registersV[$registerX] = $result & 0xFF;
        $this->registerVF = $this->registersV[$registerX] > $this->registersV[$registerY] ? 1 : 0;
    }

    /**
     * 8xy6 - SHR Vx {, Vy}
     */
    private function opShiftRight(int $opcode) 
    {
        $registerX = $opcode >> 8 & 0x0F;
        $this->registerVF = $this->registersV[$registerX] & 0x01;
        $this->registersV[$registerX] >>= 1;
    }

    /**
     * 8xy7 - SUBN Vx, Vy
     */
    private function opSubNRegister(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        $result = $this->registersV[$registerY] - $this->registersV[$registerX];
        $this->registersV[$registerX] = $result & 0xFF;
        $this->registerVF = $this->registersV[$registerY] > $this->registersV[$registerX] ? 1 : 0;
    }

    /**
     * 8xyE - SHL Vx {, Vy}
     */
    private function opShiftLeft(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $this->registerVF = $this->registersV[$registerX] >> 7;
        $this->registersV[$registerX] <<= 1;
    }

    /**
     * 9xy0 - SNE Vx, Vy
     */
    private function opSkipIfNotEqualRegisters(int $opcode)
    {
        $registerX = $opcode >> 8 & 0x0F;
        $registerY = $opcode >> 4 & 0x0F;
        
        if ($this->registersV[$registerX] !== $this->registersV[$registerY]) {
            $this->programCounter += 2;
        }
    }

    /**
     * Annn - LD I, addr 
     */
    private function opLoadI(int $opcode)
    {
        $this->registerI = $opcode & 0x0FFF;
    }

    /**
     * Bnnn - JP V0, addr
     */
    private function opJumpOffset(int $opcode)
    {
        $address = $opcode & 0x0FFF;
        $this->programCounter = $this->registersV[0] + $address;
    }

    /**
     * Cxkk - RND Vx, byte
     */
    private function opRandomAnd(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $value = $opcode & 0x00FF;
        $this->registersV[$register] = rand(0, 255) & $value;
    }

    /**
     * Dxyn - DRW Vx, Vy, nibble
     */
    private function opDrawSprite(int $opcode)
    {
        $x = $this->registersV[$opcode >> 8 & 0x0F];
        $y = $this->registersV[$opcode >> 4 & 0x0F];
        $height = $opcode & 0x000F;

        $this->registerVF = 0;

        for ($yline = 0; $yline < $height; $yline++) {
            $pixel = $this->memory->blob[$this->registerI + $yline];
            for ($xline = 0; $xline < 8; $xline++) {
                if (($pixel & (0x80 >> $xline)) !== 0) {
                    if ($this->monitor->getPixel($x + $xline, $y + $yline) === 1) {
                        $this->registerVF = 1;
                    }
                    $this->monitor->setPixel($x + $xline, $y + $yline, $this->monitor->getPixel($x + $xline, $y + $yline) ^ 1);
                }
            }
        }
    }

    /**
     * Ex9E - SKP Vx
     */
    private function opSkipIfKeyPressed(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        if ($this->keyPressStates[$this->registersV[$register]] === 1) {
            $this->programCounter += 2;
        }
    }

    /**
     * ExA1 - SKNP Vx
     */
    private function opSkipIfKeyNotPressed(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        if ($this->keyPressStates[$this->registersV[$register]] === 0) {
            $this->programCounter += 2;
        }
    }

    /**
     * Fx07 - LD Vx, DT
     */
    private function opLoadDelayTimer(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $this->registersV[$register] = $this->timers[0];
    }

    /**
     * Fx0A - LD Vx, K
     */
    private function opWaitForKeyPress(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $keyPressed = false;
        for ($i = 0; $i < 16; $i++) {
            if ($this->keyPressStates[$i] === 1) {
                $this->registersV[$register] = $i;
                $keyPressed = true;
                break;
            }
        }

        if (!$keyPressed) {
            $this->programCounter -= 2;
        }
    }

    /**
     * Fx15 - LD DT, Vx
     */
    private function opSetDelayTimer(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $this->timers[0] = $this->registersV[$register];
    }

    /**
     * Fx18 - LD ST, Vx
     */
    private function opSetSoundTimer(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $this->timers[1] = $this->registersV[$register];
    }

    /**
     * Fx1E - ADD I, Vx
     */
    private function opAddToI(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $this->registerI += $this->registersV[$register];
    }

    /**
     * Fx29 - LD F, Vx
     */
    private function opLoadFont(int $opcode)
    {
        // todo
    }

    /**
     * Fx33 - LD B, Vx
     */
    private function opStoreBCD(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        $value = $this->registersV[$register];
        $this->memory->blob[$this->registerI] = $value / 100;
        $this->memory->blob[$this->registerI + 1] = ($value / 10) % 10;
        $this->memory->blob[$this->registerI + 2] = $value % 10;
    }

    /**
     * Fx55 - LD [I], Vx
     */
    private function opStoreRegisters(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        for ($i = 0; $i <= $register; $i++) {
            $this->memory->blob[$this->registerI + $i] = $this->registersV[$i];
        }
    }

    /**
     * Fx65 - LD Vx, [I]
     */
    private function opLoadRegisters(int $opcode)
    {
        $register = $opcode >> 8 & 0x0F;
        for ($i = 0; $i <= $register; $i++) {
            $this->registersV[$i] = $this->memory->blob[$this->registerI + $i];
        }
    }


    private function handleRegisterOpcodes(int $opcode)
    {
        $operation = $opcode & 0x000F;

        match($operation) {
            0x0 => $this->opLoadRegister($opcode),
            0x1 => $this->opOrRegister($opcode),
            0x2 => $this->opAndRegister($opcode),
            0x3 => $this->opXorRegister($opcode),
            0x4 => $this->opAddRegister($opcode),
            0x5 => $this->opSubRegister($opcode),
            0x6 => $this->opShiftRight($opcode),
            0x7 => $this->opSubNRegister($opcode),
            0xE => $this->opShiftLeft($opcode),
        };
    }

    private function handleKeyOpcodes(int $opcode)
    {
        $operation = $opcode & 0x00FF;

        match($operation) {
            0x9E => $this->opSkipIfKeyPressed($opcode),
            0xA1 => $this->opSkipIfKeyNotPressed($opcode),
        };
    }

    private function handleTimerOpcodes(int $opcode)
    {
        $operation = $opcode & 0x00FF;

        match($operation) {
            0x07 => $this->opLoadDelayTimer($opcode),
            0x0A => $this->opWaitForKeyPress($opcode),
            0x15 => $this->opSetDelayTimer($opcode),
            0x18 => $this->opSetSoundTimer($opcode),
            0x1E => $this->opAddToI($opcode),
            0x29 => $this->opLoadFont($opcode),
            0x33 => $this->opStoreBCD($opcode),
            0x55 => $this->opStoreRegisters($opcode),
            0x65 => $this->opLoadRegisters($opcode),
        };
    }

    public function executeOpcode(int $opcode)
    {
        $operation = ($opcode >> 12) & 0x0F;

        match($operation) {
            0x0 => $this->opNotImplemented($opcode),
            0x1 => $this->opJump($opcode),
            0x2 => $this->opCallSubroutine($opcode),
            0x3 => $this->opSkipIfEqual($opcode),
            0x4 => $this->opSkipIfNotEqual($opcode),
            0x5 => $this->opSkipIfEqualRegisters($opcode),
            0x6 => $this->opLoadValue($opcode),
            0x7 => $this->opAddValue($opcode),
            0x8 => $this->handleRegisterOpcodes($opcode),
            0x9 => $this->opSkipIfNotEqualRegisters($opcode),
            0xA => $this->opLoadI($opcode),
            0xB => $this->opJumpOffset($opcode),
            0xC => $this->opRandomAnd($opcode),
            0xD => $this->opDrawSprite($opcode),
            0xE => $this->handleKeyOpcodes($opcode),
            0xF => $this->handleTimerOpcodes($opcode),
        };
    }
}