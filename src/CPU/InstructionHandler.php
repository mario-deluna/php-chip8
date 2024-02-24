<?php 

namespace App\CPU;

use App\CPU;

abstract class InstructionHandler 
{
    /**
     * @param int $code The code this operation is associated with
     */
    public function __construct()
    {}

    abstract public function disassemble(int $opcode): ?string;

    abstract public function handle(CPU $cpu, int $opcode): void;
}