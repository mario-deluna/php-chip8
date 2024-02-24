<?php 

namespace App\CPU;

use App\CPU;

class RegistryInstructionHandler extends InstructionHandler
{
    /**
     * @param int $code The code this operation is associated with
     */
    public function __construct(
        private InstructionRegistry $registry
    )
    {}

    public function disassemble(int $opcode): ?string
    {
        $handler = $this->registry->getHandler($opcode);
        return $handler ? $handler->disassemble($opcode) : null;
    }

    public function handle(CPU $cpu, int $opcode): void
    {
        $this->registry->getHandler($opcode)?->handle($cpu, $opcode);
    }
}