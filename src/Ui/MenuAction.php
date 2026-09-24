<?php

declare(strict_types=1);

namespace SnakeCode\Ui;

/**
 * Opções do menu inicial.
 */
enum MenuAction
{
    case Resume;
    case NewGame;
    case Stealth;
    case Settings;
    case Quit;
}
