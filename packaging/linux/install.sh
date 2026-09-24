#!/bin/sh
# Instala o SnakeCode para o usuário atual (sem sudo) em $PREFIX (padrão: ~/.local):
#   $PREFIX/share/snakecode/{snakecode,snakecode.phar}  e o link $PREFIX/bin/snakecode
set -eu

PREFIX=${PREFIX:-"$HOME/.local"}
SRC=$(cd "$(dirname "$0")" && pwd -P)
DEST="$PREFIX/share/snakecode"

mkdir -p "$DEST" "$PREFIX/bin"

# Copia para um nome temporário e troca com mv: nunca fica um arquivo pela metade.
for file in snakecode.phar snakecode; do
    cp "$SRC/$file" "$DEST/$file.new"
    chmod 755 "$DEST/$file.new"
    mv -f "$DEST/$file.new" "$DEST/$file"
done
ln -sf "$DEST/snakecode" "$PREFIX/bin/snakecode"

echo "SnakeCode instalado em $DEST"
case ":$PATH:" in
    *":$PREFIX/bin:"*) ;;
    *) echo "Adicione ao seu ~/.bashrc:  export PATH=\"$PREFIX/bin:\$PATH\"" ;;
esac
echo "Uso: snakecode [pasta-do-projeto]"
