<?php 

namespace App;

use GL\Buffer\UByteBuffer;

class Memory
{
    public UByteBuffer $blob;

    public function __construct(
        private int $size = 4096
    )
    {
        $this->blob = new UByteBuffer();
        $this->reset();
    }

    public function reset() 
    {
        $this->blob->fill($this->size, 0x0);
    }

    public function storeOpcode(int $address, int $opcode)
    {
        $this->blob[$address] = $opcode >> 8;
        $this->blob[$address + 1] = $opcode & 0x00FF;
    }

    public function fetchSprite(int $address, int $length): UByteBuffer
    {
        $sprite = new UByteBuffer();
        $sprite->fill($length, 0x0);

        for ($i = 0; $i < $length; $i++) {
            $sprite[$i] = $this->blob[$address + $i];
        }

        return $sprite;
    }
}