#!/bin/sh
# Purple Festival Gujarat 2027 — Safe Non-Destructive Bluehost Staging Deployment Script
#
# Invoked automatically by `.cpanel.yml` (cPanel Git Version Control) or manually via SSH:
#   sh scripts/deploy-staging.sh [/explicit/non-overlapping/staging/docroot]
#
# Safety guarantees:
# 1. Refuses to run (exits 0 safely without modifying or deleting any files) if no
#    explicit staging target is configured in `$HOME/.pfg27_staging_docroot` (or `$1`).
# 2. Refuses to run (exits 0 safely) if the Git repository directory and deployment target
#    directory are identical or overlap in either direction (prevents self-copy & deletion).
# 3. Never executes `rm -rf` against the repository or target document root.
# 4. Uses `scripts/build-release-package.php` to copy strictly allowlisted runtime files
#    into a temporary build directory outside the webroot, then syncs them to the staging
#    docroot without touching `$HOME/.pfg27_env.php`, databases, or unrelated websites.
# 5. Automatically re-prepends `$HOME/pfg27_auth/staging_htaccess_header.conf` (if present)
#    so staging Basic Auth and `X-Robots-Tag: noindex, nofollow` are never lost on deploy.

set -eu

REPO_DIR="$(pwd -P)"
HOME_DIR="${HOME:-$(cd ~ && pwd -P)}"
CONFIG_FILE="${HOME_DIR}/.pfg27_staging_docroot"

RAW_TARGET="${1:-}"
if [ -z "$RAW_TARGET" ] && [ -r "$CONFIG_FILE" ]; then
    RAW_TARGET="$(head -n 1 "$CONFIG_FILE" | tr -d '\r' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
fi

if [ -z "$RAW_TARGET" ]; then
    printf '%s\n' "[pfg27-deploy] No separate staging target configured in ${CONFIG_FILE}. Skipping automatic deployment safely (zero files modified)."
    exit 0
fi

if [ ! -d "$RAW_TARGET" ]; then
    printf '%s\n' "[pfg27-deploy] Configured staging target does not exist: ${RAW_TARGET}. Skipping safely (zero files modified)."
    exit 0
fi

TARGET_DIR="$(cd "$RAW_TARGET" && pwd -P)"

# Hard safety guards against root, home, identical, or overlapping paths
if [ -z "$TARGET_DIR" ] || [ "$TARGET_DIR" = "/" ] || [ "$TARGET_DIR" = "$HOME_DIR" ]; then
    printf '%s\n' "[pfg27-deploy] SAFETY ABORT: Refusing unsafe target directory (${TARGET_DIR})."
    exit 0
fi

case "${TARGET_DIR}/" in
    "${REPO_DIR}/"*)
        printf '%s\n' "[pfg27-deploy] SAFETY ABORT: Target (${TARGET_DIR}) is identical to or inside repository (${REPO_DIR}). Refusing self-copy."
        exit 0
        ;;
esac

case "${REPO_DIR}/" in
    "${TARGET_DIR}/"*)
        printf '%s\n' "[pfg27-deploy] SAFETY ABORT: Repository (${REPO_DIR}) is inside target (${TARGET_DIR}). Refusing self-copy."
        exit 0
        ;;
esac

# Discover PHP 8.1+ CLI binary on Bluehost cPanel
PHP_BIN=""
for candidate in \
    "$(command -v ea-php82 2>/dev/null || true)" \
    "/usr/local/bin/ea-php82" \
    "/opt/cpanel/ea-php82/root/usr/bin/php" \
    "$(command -v ea-php83 2>/dev/null || true)" \
    "/usr/local/bin/ea-php83" \
    "$(command -v php 2>/dev/null || true)"; do
    if [ -n "$candidate" ] && [ -x "$candidate" ]; then
        if "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' >/dev/null 2>&1; then
            PHP_BIN="$candidate"
            break
        fi
    fi
done

if [ -z "$PHP_BIN" ]; then
    printf '%s\n' "[pfg27-deploy] ERROR: No PHP >= 8.1 CLI binary found on server." >&2
    exit 1
fi

RELEASE_BASE="${HOME_DIR}/pfg27_releases"
STAGE_DIR="${RELEASE_BASE}/cpanel_stage_$$"
mkdir -p "$RELEASE_BASE"
chmod 700 "$RELEASE_BASE" 2>/dev/null || true

cleanup() {
    if [ -n "${STAGE_DIR:-}" ] && [ -d "$STAGE_DIR" ]; then
        rm -rf "$STAGE_DIR"
    fi
}
trap cleanup EXIT

printf '%s\n' "[pfg27-deploy] Building verified runtime package from ${REPO_DIR} using ${PHP_BIN}..."
"$PHP_BIN" "${REPO_DIR}/scripts/build-release-package.php" --out="$STAGE_DIR" --json >/dev/null

if [ ! -f "${STAGE_DIR}/index.php" ] || [ ! -f "${STAGE_DIR}/.htaccess" ]; then
    printf '%s\n' "[pfg27-deploy] ERROR: Release build did not produce expected runtime files." >&2
    exit 1
fi

# Preserve staging-only .htaccess access gate if provisioned outside webroot
GATE_HEADER="${HOME_DIR}/pfg27_auth/staging_htaccess_header.conf"
if [ -r "$GATE_HEADER" ]; then
    TMP_HT="${STAGE_DIR}/.htaccess.merged"
    cat "$GATE_HEADER" > "$TMP_HT"
    printf '\n' >> "$TMP_HT"
    cat "${STAGE_DIR}/.htaccess" >> "$TMP_HT"
    mv "$TMP_HT" "${STAGE_DIR}/.htaccess"
    printf '%s\n' "[pfg27-deploy] Preserved staging .htaccess gate from ${GATE_HEADER}."
fi

# Non-destructive runtime sync (never passes --delete against docroot)
if command -v rsync >/dev/null 2>&1; then
    rsync -a "${STAGE_DIR}/" "${TARGET_DIR}/"
else
    cp -a "${STAGE_DIR}/." "${TARGET_DIR}/"
fi

printf '%s\n' "[pfg27-deploy] Deployed verified runtime files cleanly to ${TARGET_DIR}."
