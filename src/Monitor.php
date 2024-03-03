<?php 

namespace App;

use GL\Buffer\UByteBuffer;

class Monitor
{
    public UByteBuffer $blob;

    public function __construct(
        public readonly int $width = 64,
        public readonly int $height = 32
    )
    {
        $this->blob = new UByteBuffer();
        $this->reset();
    }

    public function reset()
    {
        $this->blob->fill($this->width * $this->height * 2, 0x00);
    }

    public function setPixel(int $x, int $y, int $value)
    {
        $this->blob[$x + $y * $this->width] = $value;
    }

    public function getPixel(int $x, int $y): int
    {
        return $this->blob[$x + $y * $this->width] ?: 0;
    }
}