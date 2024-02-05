<?php 

namespace App;

class Program
{
    public static function opJump(int $address)
    {
        return 0x1000 | $address;
    }

    public static function opDrawPixel(int $registerX, int $registerY, int $registerColor)
    {
        return 0x2000 | ($registerX << 8) | ($registerY << 4) | $registerColor;
    }

    public static function opRandom(int $register)
    {
        return 0x3000 | ($register << 8);
    }
}