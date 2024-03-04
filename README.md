# PHP ChipEight Emulator

I don't know how many turing mashines deep this is but here is a Chip8 Emulator written in PHP. 

Built on [PHP-GLFW](http://github.com/mario-deluna/php-glfw) and the [VISU](https://github.com/phpgl/visu) framework.

![demo](https://github.com/mario-deluna/php-chip8/assets/956212/ae03baa0-8a00-4872-8131-39ca420a6310)

* [Features](#features)
* [FAQ](#faq)
* [How To use it ](#how-to-use-it)
* [Credits](#credits)
* [License](#license)

## Features

* **It runs Chip8 ROMs!**  
  This is obvious, but the moment it started working was magical to me, so I'm putting it here.
* **Debugger**  
  You can step through the code and see the state of the registers and memory.  
  Press `space` to pause or hit the _Pause button_.
  ![stepper](https://github.com/mario-deluna/php-chip8/assets/956212/dbcf3e7c-4652-4f5e-a05e-21e96745d978)
* **Drag and Drop**<br>As seen in the demo, you can simply drag and drop your ROMs onto the emulator.
* **Fullscreen mode**<br>
  If you don't care for all the debugging stuff, you can simply go fullscreen and enjoy the game.
* **Virtual Keyboard**  
  You can use the keyboard in the GUI or use the keybindings:

  <img width="275" alt="keyboard" src="https://github.com/mario-deluna/php-chip8/assets/956212/8dd18c0c-3c2b-4ba4-a21a-5da98386a54d">

  |   |   |   |   |    |   |   |   |   |
  |---|---|---|---|----|---|---|---|---|
  | 1 | 2 | 3 | C | `->` | 1 | 2 | 3 | 4 |
  | 4 | 5 | 6 | D | `->` | q | w | e | r |
  | 7 | 8 | 9 | E | `->` | a | s | d | f |
  | A | 0 | B | F | `->` | y | x | c | v |
* **Ghosting Effect**  
  I'm honestly not very knowledgeable about old hardware, but I'm assuming that the old CRTs would take some time for the phosphor to fade out. I tried to emulate this effect by adding a ghosting effect to the display, as the flickering otherwise is quite unpleasant.  
  You can change the strength of the effect.
  ![ghosting](https://github.com/mario-deluna/php-chip8/assets/956212/b0014e39-ed44-4bde-a6a1-2f314502a21c)

* **CRT Effect**  
  Some might hate this effect; I like it, but of course, you can turn it off.
  ![crt](https://github.com/mario-deluna/php-chip8/assets/956212/5d2c269c-55fd-481a-8699-0695c258b978)

* **GUI!**
  The GUI is created using an immediate mode style of drawing. You get results really quickly, the code is really ugly and performance is really not great. 

## FAQ
 
 * **What is this?**<br>
   It is a Chip8 Emulator written in PHP.
 * **Can I use it in Production?**<br>
   Of course! Its beyond my understanding why you would want to do that, but sure go for it!
 * **Why did you make this?**<br>
   Yes! 
 * **Thats not a real answer**<br>
   Yes! 
 * **Is this a real FAQ?**<br>
   Yes!
 * **Yes!**<br>
   No!

## How To use it 

1. Clone the repository<br>
   ```
   $ git clone git@github.com:mario-deluna/php-chip8.git
   ```
2. Install the dependencies<br>
   ```
   $ cd php-chip8
   $ composer install
   ```
3. Double check [PHP-GLFW](http://github.com/mario-deluna/php-glfw) is properly installed.
4. Run the emulator<br>
   ```
   $ php bin/start.php
   ```

## Credits

- [Mario Döring](https://github.com/mario-deluna)
- [All Contributors](https://github.com/mario-deluna/php-chip8/contributors)

## License

Please see [License File](https://github.com/mario-deluna/php-chip8/blob/master/LICENSE) for more information.