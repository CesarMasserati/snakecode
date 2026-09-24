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
    /** Game over: painel TERMINAL com o stack trace da colisão. */
    case Crashed;
}
