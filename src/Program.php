<?php 

namespace App;

class Program
{
    public static function opClearScreen()
    {
        return 0x00E0;
    }

    public static function opExit()
    {
        return 0x00FD;
    }

    public static function opReturn()
    {
        return 0x00EE;
    }

    public static function opCall(int $address)
    {
        return 0x2000 | $address;
    }

    public static function opJump(int $address)
    {
        return 0x1000 | $address;
    }

    public static function opSkipIfEqualValue(int $register, int $value)
    {
        return 0x3000 | ($register << 8) | $value;
    }

    public static function opSkipIfNotEqualValue(int $register, int $value)
    {
        return 0x4000 | ($register << 8) | $value;
    }

    public static function opSkipIfEqualRegister(int $registerX, int $registerY)
    {
        return 0x5000 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opLoadValue(int $register, int $value)
    {
        return 0x6000 | ($register << 8) | $value;
    }

    public static function opAddValue(int $register, int $value)
    {
        return 0x7000 | ($register << 8) | $value;
    }

    public static function opLoadRegister(int $registerX, int $registerY)
    {
        return 0x8000 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opOr(int $registerX, int $registerY)
    {
        return 0x8001 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opAnd(int $registerX, int $registerY)
    {
        return 0x8002 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opXor(int $registerX, int $registerY)
    {
        return 0x8003 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opAddRegister(int $registerX, int $registerY)
    {
        return 0x8004 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opSubRegister(int $registerX, int $registerY)
    {
        return 0x8005 | ($registerX << 8) | ($registerY << 4);
    }

    public static function opShiftRight(int $registerX)
    {
        return 0x8006 | ($registerX << 8);
    }

    public static function opSubNRegister(int $registerX, int $registerY)
    {
        return 0x8007 | ($registerX << 8) | ($registerY << 4);
    }
}