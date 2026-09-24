<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

/**
 * Comandos de alto nível produzidos a partir das teclas pressionadas.
 */
enum Command
{
    case Up;
    case Down;
    case Left;
    case Right;
    case Pause;
    case Stealth;
    case Panic;
    case Quit;
    case Confirm;
    case Menu;
}
