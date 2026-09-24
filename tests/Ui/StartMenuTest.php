<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Ui;

use PHPUnit\Framework\TestCase;
use SnakeCode\Render\Theme;
use SnakeCode\Ui\MenuAction;
use SnakeCode\Ui\StartMenu;

final class StartMenuTest extends TestCase
{
    public function testFirstLaunchOffersStartStealthAndQuit(): void
    {
        $menu = new StartMenu();

        self::assertSame([MenuAction::NewGame, MenuAction::Stealth, MenuAction::Quit], $menu->items());
        self::assertSame('Iniciar', $menu->label(MenuAction::NewGame, new Theme()));
        self::assertFalse($menu->canResume());
    }

    public function testGameInProgressOffersResumeFirst(): void
    {
        $menu = new StartMenu(canResume: true, hasPlayed: true);

        self::assertSame(MenuAction::Resume, $menu->current());
        self::assertSame('Nova partida', $menu->label(MenuAction::NewGame, new Theme()));
    }

    public function testNavigationWrapsAround(): void
    {
        $menu = new StartMenu();

        $menu->up();
        self::assertSame(MenuAction::Quit, $menu->current());
        $menu->down();
        self::assertSame(MenuAction::NewGame, $menu->current());
        $menu->down();
        self::assertSame(MenuAction::Stealth, $menu->current());
    }

    public function testStealthLabelShowsCurrentProfile(): void
    {
        $menu = new StartMenu();

        self::assertSame('Discrição: ◀ medium ▶', $menu->label(MenuAction::Stealth, new Theme('medium')));
        self::assertSame('easy', (new Theme('subtle'))->shifted(-1)->profile);
        self::assertSame('subtle', (new Theme('easy'))->shifted(1)->profile);
    }
}
