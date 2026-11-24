#!/usr/bin/env sh
set -eu

project=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
output=${1:-"$project/dist/binprofkes-cpanel.zip"}
revision=${2:-HEAD}
case "$output" in
    /*) ;;
    *) output="$PWD/$output" ;;
esac
mkdir -p -- "$(dirname -- "$output")"
temporary=$(mktemp "${output}.XXXXXX")
trap 'rm -f -- "$temporary"' EXIT
commit=$(git -C "$project" rev-parse --verify --end-of-options "$revision^{commit}")

git -C "$project" archive --format=zip --prefix=binprofkes/ -o "$temporary" "$commit" \
    README.md TASKS.md PRD.md PROMPT_CODEX.md brainstorming.md \
    app cli config/example.php database/binprofkes.sql database/migrations docs \
    public vendor-exports vendor-assets/README.md vendor-assets/package.json vendor-assets/package-lock.json \
    vendor-assets/build-outline.py vendor-assets/world-atlas-LICENSE
mv -T -- "$temporary" "$output"
printf 'Paket %s (%s)\n' "$output" "$commit"
