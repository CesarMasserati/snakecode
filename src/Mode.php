<?php

declare(strict_types=1);

namespace SnakeCode;

/**
 * Estado do laço principal.
 */
enum Mode
{
    /** Menu inicial (aba "Welcome") com opções e instruções. */
    case Menu;
    /** Parado aguardando uma direção ("Paused on breakpoint"). */
    case Paused;
    case Running;
    /** Tecla de pânico: congela o jogo e exibe apenas código. */
    case Panic;
    case Settings;
    case SettingsPath;
    /** Game over: painel TERMINAL com o stack trace da colisão. */
    case Crashed;
    /** Vitória só acontece quando o nível 100 ocupa todo o tabuleiro. */
    case Won;
}
