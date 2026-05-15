#!/usr/bin/env bash
# release.sh — cut a new tagged release.
#
# Usage:
#   ./release.sh                        # default: bump next minor from latest tag
#   ./release.sh 0.2.0                  # explicit version
#   ./release.sh 0.2.0 --dry-run        # show what would happen, change nothing
#   ./release.sh 0.2.0 --skip-tests     # skip pest + phpstan + pint (not recommended)
#   ./release.sh 0.2.0 --skip-gh        # do not create the GitHub release
#
# Requires: git, gh (https://cli.github.com), composer, php.
#
# What it does:
#   1. Validates version (semver) and working tree (clean, on main, up to date).
#   2. Runs composer validate, Pint --test, PHPStan, Pest.
#   3. Rewrites CHANGELOG.md: promotes the [Unreleased] section to the
#      new version with today's date, leaves a fresh empty [Unreleased]
#      stub on top, and rewrites the compare/tag link footnotes.
#   4. Commits the CHANGELOG change as "chore(release): vX.Y.Z".
#   5. Tags the commit with vX.Y.Z (annotated, GPG-signed if configured).
#   6. Pushes branch and tag to origin.
#   7. Creates a GitHub release with the section notes from CHANGELOG.

set -Eeuo pipefail

# ---------- helpers ----------
red()   { printf '\033[31m%s\033[0m\n' "$*" >&2; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
blue()  { printf '\033[34m%s\033[0m\n' "$*"; }
yellow(){ printf '\033[33m%s\033[0m\n' "$*"; }

die() { red "✗ $*"; exit 1; }

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

# ---------- args ----------
VERSION=""
DRY_RUN=0
SKIP_TESTS=0
SKIP_GH=0

for arg in "$@"; do
    case "$arg" in
        --dry-run)    DRY_RUN=1 ;;
        --skip-tests) SKIP_TESTS=1 ;;
        --skip-gh)    SKIP_GH=1 ;;
        -*)           die "Unknown flag: $arg" ;;
        *)
            [[ -z "$VERSION" ]] || die "Multiple version arguments given: '$VERSION' and '$arg'"
            VERSION="$arg"
            ;;
    esac
done

# Default: bump next minor from the latest vX.Y.Z tag (0.1.0 if no tags).
if [[ -z "$VERSION" ]]; then
    LATEST_TAG="$(git tag --list 'v*' --sort=-v:refname | head -n1)"
    if [[ -z "$LATEST_TAG" ]]; then
        VERSION="0.1.0"
        blue "▶ No existing tag found, defaulting to $VERSION"
    else
        LATEST_VERSION="${LATEST_TAG#v}"
        [[ "$LATEST_VERSION" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+) ]] \
            || die "Latest tag '$LATEST_TAG' is not parseable semver."
        VERSION="${BASH_REMATCH[1]}.$((BASH_REMATCH[2] + 1)).0"
        blue "▶ No version given, bumping next minor: $LATEST_TAG → v$VERSION"
    fi
fi

# Strip leading 'v' if the user passed it.
VERSION="${VERSION#v}"

[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]] \
    || die "Version must be semver (e.g. 0.2.0 or 1.0.0-rc.1). Got: $VERSION"

TAG="v${VERSION}"
TODAY="$(date +%Y-%m-%d)"

run() {
    if [[ $DRY_RUN -eq 1 ]]; then
        yellow "DRY-RUN: $*"
    else
        eval "$@"
    fi
}

# ---------- preflight ----------
require_cmd git
require_cmd composer
require_cmd php
[[ $SKIP_GH -eq 1 ]] || require_cmd gh

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

blue "▶ Pre-flight checks"

# Working tree must be clean.
[[ -z "$(git status --porcelain)" ]] \
    || die "Working tree is not clean. Commit or stash first."

# Must be on the main branch.
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[[ "$CURRENT_BRANCH" == "main" ]] \
    || die "Not on 'main' (currently on '$CURRENT_BRANCH'). Switch with: git checkout main"

# Must be up to date with origin/main.
git fetch --quiet origin main
LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/main)"
[[ "$LOCAL" == "$REMOTE" ]] \
    || die "Local main is not in sync with origin/main. Pull/push first."

# Tag must not already exist.
git rev-parse "$TAG" >/dev/null 2>&1 \
    && die "Tag $TAG already exists."

# CHANGELOG must have an [Unreleased] section.
grep -q '^## \[Unreleased\]' CHANGELOG.md \
    || die "CHANGELOG.md is missing an '## [Unreleased]' section."

# That section must not be empty (a release without notes is a bug).
UNRELEASED_BODY="$(awk '/^## \[Unreleased\]/{flag=1;next} /^## \[/{flag=0} flag' CHANGELOG.md | sed '/^$/d')"
[[ -n "$UNRELEASED_BODY" ]] \
    || die "'## [Unreleased]' section is empty. Add release notes first."

green "✓ Working tree clean, on main, up to date with origin"
green "✓ Tag $TAG is available"
green "✓ CHANGELOG has notes under [Unreleased]"

# ---------- quality gates ----------
if [[ $SKIP_TESTS -eq 0 ]]; then
    blue "▶ Quality gates"
    run "composer validate --strict"
    run "vendor/bin/pint --test"
    run "vendor/bin/phpstan analyse --memory-limit=512M"
    run "vendor/bin/pest"
    green "✓ All gates passed"
else
    yellow "⚠ Skipping quality gates (--skip-tests)"
fi

# ---------- rewrite CHANGELOG ----------
blue "▶ Promoting [Unreleased] to [$VERSION] in CHANGELOG.md"

CHANGELOG_TMP="$(mktemp)"
awk -v version="$VERSION" -v today="$TODAY" '
    BEGIN { promoted = 0 }
    /^## \[Unreleased\]/ && !promoted {
        print "## [Unreleased]"
        print ""
        print "## [" version "] - " today
        promoted = 1
        next
    }
    { print }
' CHANGELOG.md > "$CHANGELOG_TMP"

# Rewrite the link footnotes at the bottom of the file.
# Replace previous [Unreleased] link target with new compare against this tag,
# and insert a new [VERSION] link target.
NEW_CHANGELOG="$(mktemp)"
awk -v version="$VERSION" '
    /^\[Unreleased\]:/ {
        sub(/v[0-9]+\.[0-9]+\.[0-9]+\.\.\.HEAD/, "v" version "...HEAD")
        print
        print "[" version "]: https://github.com/Chemaclass/laravel-feature-flags/releases/tag/v" version
        next
    }
    { print }
' "$CHANGELOG_TMP" > "$NEW_CHANGELOG"

if [[ $DRY_RUN -eq 1 ]]; then
    yellow "DRY-RUN: would write the following CHANGELOG.md:"
    diff -u CHANGELOG.md "$NEW_CHANGELOG" || true
    NOTES_SOURCE="$NEW_CHANGELOG"
else
    mv "$NEW_CHANGELOG" CHANGELOG.md
    rm -f "$CHANGELOG_TMP"
    NOTES_SOURCE="CHANGELOG.md"
fi

# ---------- extract release notes for gh ----------
RELEASE_NOTES_FILE="$(mktemp)"
awk -v version="$VERSION" '
    $0 ~ "^## \\[" version "\\]" { flag = 1; next }
    /^## \[/ && flag { exit }
    flag { print }
' "$NOTES_SOURCE" \
  | sed '/./,$!d' \
  | awk 'NF { lines = lines $0 ORS; next } { lines = lines ORS } END { sub(/\n+$/, "", lines); print lines }' \
  > "$RELEASE_NOTES_FILE"

if [[ ! -s "$RELEASE_NOTES_FILE" ]]; then
    die "Failed to extract release notes for $VERSION."
fi

green "✓ Release notes extracted ($(wc -l < "$RELEASE_NOTES_FILE") lines)"

# ---------- commit, tag, push ----------
blue "▶ Committing, tagging, pushing"

run "git add CHANGELOG.md"
run "git commit -m 'chore(release): $TAG'"
run "git tag -a $TAG -m '$TAG'"
run "git push origin main"
run "git push origin $TAG"

green "✓ Pushed commit and tag $TAG"

# ---------- gh release ----------
if [[ $SKIP_GH -eq 1 ]]; then
    yellow "⚠ Skipping GitHub release (--skip-gh)"
else
    blue "▶ Creating GitHub release"
    run "gh release create $TAG --title '$TAG' --notes-file '$RELEASE_NOTES_FILE'"
    green "✓ GitHub release $TAG created"
fi

rm -f "$RELEASE_NOTES_FILE"

echo
green "🎉 Released $TAG"
echo "   Tag:     $TAG"
echo "   Branch:  main"
if [[ $SKIP_GH -eq 0 ]]; then
    echo "   GitHub:  https://github.com/Chemaclass/laravel-feature-flags/releases/tag/$TAG"
fi
echo "   Packagist will pick up the tag automatically if the webhook is configured."
