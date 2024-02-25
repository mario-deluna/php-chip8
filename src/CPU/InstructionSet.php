<?php 

namespace App\CPU;

use App\CPU;

class InstructionSet
{   
    /**
     * Returns an InstructionRegistry with the default instruction set to run the CHIP-8 interpreter
     */
    public static function defaultSet() : InstructionRegistry
    {
        $x00Instructions = new InstructionRegistry(
            bitmask: 0x00FF,
            handlers: [
                0xE0 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return 'CLS';
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $cpu->monitor->blob->fill($cpu->monitor->blob->size(), 0);
                    }
                },

                0xEE => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return 'RET';
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $cpu->programCounter = $cpu->stack[--$cpu->stackPointer];
                    }
                },

                0xFD => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return 'EXIT';
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $cpu->shouldExit = true;
                    }
                },
            ]
        );

        $registerInstructions = new InstructionRegistry(
            bitmask: 0x000F,
            handlers: [
                /**
                 * 8xy0 - LD Vx, Vy
                 * Set Vx = Vy
                 */
                0x0 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $cpu->registers[$registerX] = $cpu->registers[$registerY];
                    }
                },

                /**
                 * 8xy1 - OR Vx, Vy
                 * Set Vx = Vx OR Vy
                 */
                0x1 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('OR   V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $cpu->registers[$registerX] |= $cpu->registers[$registerY];
                    }
                },

                /**
                 * 8xy2 - AND Vx, Vy
                 * Set Vx = Vx AND Vy
                 */
                0x2 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('AND  V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $cpu->registers[$registerX] &= $cpu->registers[$registerY];
                    }
                },

                /**
                 * 8xy3 - XOR Vx, Vy
                 * Set Vx = Vx XOR Vy
                 */
                0x3 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('XOR  V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $cpu->registers[$registerX] ^= $cpu->registers[$registerY];
                    }
                },

                /**
                 * 8xy4 - ADD Vx, Vy
                 * Set Vx = Vx + Vy, set VF = carry
                 */
                0x4 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('ADD  V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $sum = $cpu->registers[$registerX] + $cpu->registers[$registerY];
                        $cpu->registers[$registerX] = $sum & 0xFF;
                        $cpu->registers[0xF] = $sum > 0xFF ? 1 : 0;
                    }
                },

                /**
                 * 8xy5 - SUB Vx, Vy
                 * Set Vx = Vx - Vy, set VF = NOT borrow
                 */
                0x5 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SUB  V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $diff = $cpu->registers[$registerX] - $cpu->registers[$registerY];
                        $cpu->registers[$registerX] = $diff & 0xFF;
                        $cpu->registers[0xF] = $diff >= 0 ? 1 : 0;
                    }
                },

                /**
                 * 8xy6 - SHR Vx {, Vy}
                 * Set Vx = Vx SHR 1
                 */
                0x6 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SHR  V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $lsb = $cpu->registers[$registerX] & 0x1;
                        $cpu->registers[$registerX] >>= 1;
                        $cpu->registers[0xF] = $lsb;
                    }
                },

                /**
                 * 8xy7 - SUBN Vx, Vy
                 * Set Vx = Vy - Vx, set VF = NOT borrow
                 */
                0x7 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SUBN V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        $diff = $cpu->registers[$registerY] - $cpu->registers[$registerX];
                        $cpu->registers[$registerX] = $diff & 0xFF;
                        $cpu->registers[0xF] = $diff >= 0 ? 1 : 0;
                    }
                },

                /**
                 * 8xyE - SHL Vx {, Vy}
                 * Set Vx = Vx SHL 1
                 */
                0xE => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SHL  V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $msb = $cpu->registers[$registerX] >> 7;
                        $cpu->registers[$registerX] <<= 1;
                        $cpu->registers[0xF] = $msb;
                    }
                },
            ]
        );

        $keyInstructions = new InstructionRegistry(
            bitmask: 0x00FF,
            handlers: [
                /**
                 * Ex9E - SKP Vx
                 * Skip next instruction if key with the value of Vx is pressed
                 */
                0x9E => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SKP  V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = $opcode >> 8 & 0x0F;
                        if ($cpu->keyPressStates[$cpu->registers[$register]] === 1) {
                            $cpu->programCounter += 2;
                        }
                    }
                },

                /**
                 * ExA1 - SKNP Vx
                 * Skip next instruction if key with the value of Vx is not pressed
                 */
                0xA1 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SKNP V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = $opcode >> 8 & 0x0F;
                        if ($cpu->keyPressStates[$cpu->registers[$register]] === 0) {
                            $cpu->programCounter += 2;
                        }
                    }
                },
            ]
        );

        $timerInstructions = new InstructionRegistry(
            bitmask: 0x00FF,
            handlers: [
                /**
                 * Fx07 - LD Vx, DT
                 * Set Vx = delay timer value
                 */
                0x07 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   V%X, DT', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $cpu->registers[$register] = $cpu->timers[0];
                    }
                },

                /**
                 * Fx0A - LD Vx, K
                 * Wait for a key press, store the value of the key in Vx
                 */
                0x0A => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   V%X, K', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $keyPressed = false;
                        for ($i = 0; $i < 16; $i++) {
                            if ($cpu->keyPressStates[$i] === 1) {
                                $cpu->registers[$register] = $i;
                                $keyPressed = true;
                                break;
                            }
                        }
                        if (!$keyPressed) {
                            $cpu->programCounter -= 2;
                        }
                    }
                },

                /**
                 * Fx15 - LD DT, Vx
                 * Set delay timer = Vx
                 */
                0x15 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   DT, V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $cpu->timers[0] = $cpu->registers[$register];
                    }
                },

                /**
                 * Fx18 - LD ST, Vx
                 * Set sound
                 * timer = Vx
                 */
                0x18 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   ST, V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $cpu->timers[1] = $cpu->registers[$register];
                    }
                },

                /**
                 * Fx1E - ADD I, Vx
                 * Set I = I + Vx
                 */
                0x1E => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('ADD  I, V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $cpu->registerI += $cpu->registers[$register];
                    }
                },

                /**
                 * Fx29 - LD F, Vx
                 * Set I = location of sprite for digit Vx
                 */
                0x29 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   F, V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $digit = $cpu->registers[$register];
                        $loc = $cpu->digitSpriteLocations[$digit];
                        $cpu->registerI = $loc;
                    }
                },

                /**
                 * Fx33 - LD B, Vx
                 * Store BCD representation of Vx in memory locations I, I+1, and I+2
                 */
                0x33 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   B, V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $value = $cpu->registers[$register];
                        
                        $cpu->memory->blob[$cpu->registerI] = (int) ($value / 100);
                        $cpu->memory->blob[$cpu->registerI + 1] = (int) (((int)($value / 10)) % 10);
                        $cpu->memory->blob[$cpu->registerI + 2] = (int) ($value % 10);
                    }
                },

                /**
                 * Fx55 - LD [I], Vx
                 * Store registers V0 through Vx in memory starting at location I
                 */
                0x55 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   [I], V%X', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $endRegister = ($opcode & 0x0F00) >> 8;
                        for ($i = 0; $i <= $endRegister; $i++) {
                            $cpu->memory->blob[$cpu->registerI + $i] = $cpu->registers[$i];
                        }
                    }
                },

                /**
                 * Fx65 - LD Vx, [I]
                 * Read registers V0 through Vx from memory starting at location I
                 */
                0x65 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   V%X, [I]', ($opcode & 0x0F00) >> 8);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $endRegister = ($opcode & 0x0F00) >> 8;
                        for ($i = 0; $i <= $endRegister; $i++) {
                            $cpu->registers[$i] = $cpu->memory->blob[$cpu->registerI + $i];
                        }
                    }
                },
            ]
        );

        return new InstructionRegistry(
            bitmask: 0x0F,
            shiftRight: 12,
            handlers: [
                0x0 => new RegistryInstructionHandler($x00Instructions),

                /**
                 * 1nnn - JP addr
                 * Jump to location nnn
                 */
                0x1 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('JP   0x%03X', $opcode & 0x0FFF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $cpu->programCounter = $opcode & 0x0FFF;
                    }
                },

                /**
                 * 2nnn - CALL addr
                 * Call subroutine at nnn
                 */
                0x2 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('CALL 0x%03X', $opcode & 0x0FFF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $address = $opcode & 0x0FFF;
                        $cpu->stack[$cpu->stackPointer++] = $cpu->programCounter;
                        $cpu->programCounter = $address;
                    }
                },

                /**
                 * 3xkk - SE Vx, byte
                 * Skip next instruction if Vx = kk
                 */
                0x3 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SE   V%X, 0x%02X', ($opcode & 0x0F00) >> 8, $opcode & 0x00FF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $value = $opcode & 0x00FF;

                        if ($cpu->registers[$register] === $value) {
                            $cpu->programCounter += 2;
                        }
                    }
                },

                /**
                 * 4xkk - SNE Vx, byte
                 * Skip next instruction if Vx != kk
                 */
                0x4 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SNE  V%X, 0x%02X', ($opcode & 0x0F00) >> 8, $opcode & 0x00FF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $value = $opcode & 0x00FF;

                        if ($cpu->registers[$register] !== $value) {
                            $cpu->programCounter += 2;
                        }
                    }
                },

                /**
                 * 5xy0 - SE Vx, Vy
                 * Skip next instruction if Vx = Vy
                 */
                0x5 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SE   V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        if ($cpu->registers[$registerX] === $cpu->registers[$registerY]) {
                            $cpu->programCounter += 2;
                        }
                    }
                },

                /**
                 * 6xkk - LD Vx, byte
                 * Set Vx = kk
                 */
                0x6 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   V%X, 0x%02X', ($opcode & 0x0F00) >> 8, $opcode & 0x00FF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $value = $opcode & 0x00FF;

                        $cpu->registers[$register] = $value;
                    }
                },

                /**
                 * 7xkk - ADD Vx, byte
                 * Set Vx = Vx + kk
                 */
                0x7 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('ADD  V%X, 0x%02X', ($opcode & 0x0F00) >> 8, $opcode & 0x00FF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $value = $opcode & 0x00FF;

                        $cpu->registers[$register] += $value;
                    }
                },

                /**
                 * 8xxx - Register instructions
                 */
                0x8 => new RegistryInstructionHandler($registerInstructions),

                /**
                 * 9xy0 - SNE Vx, Vy
                 * Skip next instruction if Vx != Vy
                 */
                0x9 => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('SNE  V%X, V%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $registerX = ($opcode & 0x0F00) >> 8;
                        $registerY = ($opcode & 0x00F0) >> 4;

                        if ($cpu->registers[$registerX] !== $cpu->registers[$registerY]) {
                            $cpu->programCounter += 2;
                        }
                    }
                },

                /**
                 * Annn - LD I, addr
                 * Set I = nnn
                 */
                0xA => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('LD   I, 0x%03X', $opcode & 0x0FFF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $cpu->registerI = $opcode & 0x0FFF;
                    }
                },

                /**
                 * Bnnn - JP V0, addr
                 * Jump to location nnn + V0
                 */
                0xB => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('JP   V0, 0x%03X', $opcode & 0x0FFF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $address = $opcode & 0x0FFF;
                        $cpu->programCounter = $cpu->registers[0] + $address;
                    }
                },

                /**
                 * Cxkk - RND Vx, byte
                 * Set Vx = random byte AND kk
                 */
                0xC => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('RND  V%X, 0x%02X', ($opcode & 0x0F00) >> 8, $opcode & 0x00FF);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $register = ($opcode & 0x0F00) >> 8;
                        $value = $opcode & 0x00FF;

                        $cpu->registers[$register] = random_int(0, 255) & $value;
                    }
                },

                /**
                 * Dxyn - DRW Vx, Vy, nibble
                 * Display n-byte sprite starting at memory location I at (Vx, Vy), set VF = collision
                 */
                0xD => new class extends InstructionHandler {
                    public function disassemble(int $opcode): string {
                        return sprintf('DRW  V%X, V%X, 0x%X', ($opcode & 0x0F00) >> 8, ($opcode & 0x00F0) >> 4, $opcode & 0x000F);
                    }

                    public function handle(CPU $cpu, int $opcode): void {
                        $x = $cpu->registers[($opcode & 0x0F00) >> 8];
                        $y = $cpu->registers[($opcode & 0x00F0) >> 4];
                        $height = $opcode & 0x000F;

                        $cpu->registers[0xF] = 0;

                        for ($yline = 0; $yline < $height; $yline++) {
                            $pixel = $cpu->memory->blob[$cpu->registerI + $yline];
                            for ($xline = 0; $xline < 8; $xline++) {
                                if (($pixel & (0x80 >> $xline)) !== 0) {
                                    if ($cpu->monitor->getPixel($x + $xline, $y + $yline) === 1) {
                                        $cpu->registers[0xF] = 1;
                                    }
                                    $cpu->monitor->setPixel($x + $xline, $y + $yline, $cpu->monitor->getPixel($x + $xline, $y + $yline) ^ 1);
                                }
                            }
                        }
                    }
                },

                /**
                 * Exxx - Key instructions
                 */
                0xE => new RegistryInstructionHandler($keyInstructions),

                /**
                 * Fxxx - Timer instructions
                 */
                0xF => new RegistryInstructionHandler($timerInstructions),
            ]
        );
    }
}