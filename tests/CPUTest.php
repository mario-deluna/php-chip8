<?php

use App\CPU;
use App\Memory;
use App\Monitor;
use App\Program;
use PHPUnit\Framework\TestCase;

class CPUTest extends TestCase
{
    private function createCPU()
    {
        return new CPU(new Memory(), new Monitor());
    }

    public function testJumpInst()
    {
        $cpu = $this->createCPU();
        $cpu->memory->storeOpcode(0x200, Program::opJump(0x250));
        $cpu->memory->storeOpcode(0x250, Program::opJump(0x200));

        $this->assertEquals('JP   0x250', $cpu->disassembleInstructionAt(0x200));
        $this->assertEquals('JP   0x200', $cpu->disassembleInstructionAt(0x250));

        $cpu->runCycles(1);

        // program counter should be 0x250
        $this->assertEquals(0x250, $cpu->programCounter);

        $cpu->runCycles(1);

        // program counter should be 0x200
        $this->assertEquals(0x200, $cpu->programCounter);
    }

    public function testSubroutineInst() 
    {
        $cpu = $this->createCPU();
        $cpu->memory->storeOpcode(0x200, Program::opClearScreen());
        $cpu->memory->storeOpcode(0x202, Program::opCall(0x250));
        $cpu->memory->storeOpcode(0x204, Program::opJump(0x200));
        $cpu->memory->storeOpcode(0x250, Program::opReturn());

        $this->assertEquals('CLS', $cpu->disassembleInstructionAt(0x200));
        $this->assertEquals('CALL 0x250', $cpu->disassembleInstructionAt(0x202));
        $this->assertEquals('JP   0x200', $cpu->disassembleInstructionAt(0x204));
        $this->assertEquals('RET', $cpu->disassembleInstructionAt(0x250));

        // now run 
        $cpu->runCycles(1);
        $this->assertEquals(0x202, $cpu->programCounter);

        $cpu->runCycles(1);
        $this->assertEquals(0x250, $cpu->programCounter);

        $cpu->runCycles(1);
        $this->assertEquals(0x204, $cpu->programCounter);

        $cpu->runCycles(1);
        $this->assertEquals(0x200, $cpu->programCounter);
    }

    public function testComparison()
    {
        $cpu = $this->createCPU();
        $cpu->memory->storeOpcode(0x200, Program::opLoadValue(0x1, 0x42));
        $cpu->memory->storeOpcode(0x202, Program::opSkipIfEqualValue(0x1, 0x42));
        $cpu->memory->storeOpcode(0x204, Program::opLoadValue(0x2, 0xFF));
        $cpu->memory->storeOpcode(0x206, Program::opExit());
        $cpu->run();

        // reister 2 should still be 0
        $this->assertEquals(0, $cpu->registers[0x2]);

        // now a not
        $cpu = $this->createCPU();
        $cpu->memory->storeOpcode(0x200, Program::opLoadValue(0x1, 0x42));
        $cpu->memory->storeOpcode(0x202, Program::opSkipIfNotEqualValue(0x1, 0x42));
        $cpu->memory->storeOpcode(0x204, Program::opLoadValue(0x2, 0xFF));
        $cpu->memory->storeOpcode(0x206, Program::opExit());
        $cpu->run();

        // reister 2 should now be 0xFF
        $this->assertEquals(0xFF, $cpu->registers[0x2]);
    }
}
