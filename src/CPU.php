<?php 

namespace App;

use App\CPU\InstructionHandler;
use App\CPU\InstructionRegistry;
use App\CPU\InstructionSet;
use Exception;
use GL\Buffer\UByteBuffer;
use GL\Buffer\UShortBuffer;

/**
 * This class simulates the Chip8 CPU, with all its registers and operations
 */
class CPU
{
    public UByteBuffer $registersV;

    public int $registerI = 0x0;

    public int $registerVF = 0x0;

    public UByteBuffer $timers;

    public int $programCounter = 0x200;

    public int $stackPointer = 0x0;

    public UShortBuffer $stack;

    public UByteBuffer $keyPressStates;

    private InstructionRegistry $instructionSet;

    public array $digitSpriteLocations = [];

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

        // load the default instruction set
        $this->instructionSet = InstructionSet::defaultSet();
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
     * Load the default font into the memory
     */
    public function loadDefaultFont() : void
    {
        $sprites = [
            0x0 => [0xF0, 0x90, 0x90, 0x90, 0xF0],
            0x1 => [0x20, 0x60, 0x20, 0x20, 0x70],
            0x2 => [0xF0, 0x10, 0xF0, 0x80, 0xF0],
            0x3 => [0xF0, 0x10, 0xF0, 0x10, 0xF0],
            0x4 => [0x90, 0x90, 0xF0, 0x10, 0x10],
            0x5 => [0xF0, 0x80, 0xF0, 0x10, 0xF0],
            0x6 => [0xF0, 0x80, 0xF0, 0x90, 0xF0],
            0x7 => [0xF0, 0x10, 0x20, 0x40, 0x40],
            0x8 => [0xF0, 0x90, 0xF0, 0x90, 0xF0],
            0x9 => [0xF0, 0x90, 0xF0, 0x10, 0xF0],
            0xA => [0xF0, 0x90, 0xF0, 0x90, 0x90],
            0xB => [0xE0, 0x90, 0xE0, 0x90, 0xE0],
            0xC => [0xF0, 0x80, 0x80, 0x80, 0xF0],
            0xD => [0xE0, 0x90, 0x90, 0x90, 0xE0],
            0xE => [0xF0, 0x80, 0xF0, 0x80, 0xF0],
            0xF => [0xF0, 0x80, 0xF0, 0x80, 0x80]
        ];

        $i = 0x050;
        foreach ($sprites as $digit => $sprite) {
            $this->digitSpriteLocations[$digit] = $i;
            foreach ($sprite as $byte) {
                $this->memory->blob[$i++] = $byte;
            }
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
     * Return the opcode at the given index
     */
    public function getOpcodeAt(int $index): int
    {
        return $this->memory->blob[$index] << 8 | $this->memory->blob[$index + 1];
    }

    /**
     * Returns the instruction handlers for the given opcodes
     * 
     * @param array<int> $opcodes
     * @return array<InstructionHandler | null>
     */
    public function getInstructionHandlersFor(array $opcodes) : array
    {
        $handlers = [];
        foreach ($opcodes as $opcode) {
            $handlers[] = $this->instructionSet->getHandler($opcode);
        }
        return $handlers;
    }

    /**
     * Returns the instruction handler for an opcode at a given index
     * 
     * @param int $index
     * @return InstructionHandler | null
     */
    public function getInstructionHandlerAt(int $index) : ?InstructionHandler
    {
        return $this->instructionSet->getHandler($this->getOpcodeAt($index));
    }

    /**
     * Returns the disassembled instruction for the opcode at the given index
     */
    public function disassembleInstructionAt(int $index) : ?string
    {
        $opcode = $this->getOpcodeAt($index);
        $handler = $this->instructionSet->getHandler($opcode);
        return $handler ? $handler->disassemble($opcode) : null;
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
    public function runCycles(int $count = 1) : int
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

    public function executeOpcode(int $opcode)
    {
        $handler = $this->instructionSet->getHandler($opcode);

        if ($handler === null) {
            throw new Exception(sprintf('Opcode 0x%X not implemented', $opcode));
        }

        $handler->handle($this, $opcode);
    }
}