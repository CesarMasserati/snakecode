#!/bin/sh
# Remove o SnakeCode instalado pelo install.sh. Recorde/preferências (~/.local/state/snakecode) são mantidos.
set -eu

PREFIX=${PREFIX:-"$HOME/.local"}

rm -f "$PREFIX/bin/snakecode"
rm -f "$PREFIX/share/snakecode/snakecode" "$PREFIX/share/snakecode/snakecode.phar"
rmdir "$PREFIX/share/snakecode" 2>/dev/null || true

echo "SnakeCode removido."
