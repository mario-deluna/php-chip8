<?php 

namespace App\CPU;

class InstructionRegistry
{
    /**
     * @param int $bitmask A bitmask that is used to match opcodes to instruction handlers
     * @param int $shiftRight The number of bits to shift the opcode to the right to get the index of the instruction handler
     * @param array<InstructionHandler> $handlers An array of instruction handlers
     */
    public function __construct(
        public readonly int $bitmask,
        public readonly int $shiftRight = 0,
        private array $handlers = []
    )
    {   
    }

    /**
     * Returns the instruction handler for the given opcode
     */
    public function getHandler(int $opcode) : ?InstructionHandler
    {
        return $this->handlers[($opcode >> $this->shiftRight) & $this->bitmask] ?? null;
    }
}