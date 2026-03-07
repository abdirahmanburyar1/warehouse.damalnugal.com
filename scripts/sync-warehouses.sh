#!/bin/bash
# Sync warehouse.damalnugal.com database and optionally files to other warehouses
# Usage: ./scripts/sync-warehouses.sh [--db-only] [--files-only] [--dry-run]

set -e

SOURCE_DIR="/var/www/warehouse.damalnugal.com"
SOURCE_ENV="${SOURCE_DIR}/.env"
DUMP_FILE="${SOURCE_DIR}/storage/app/db_dump_$(date +%Y%m%d_%H%M%S).sql"

# Target warehouses: (directory, database_name)
declare -a TARGETS=(
    "/var/www/warehouse.moh.gm.so:mohgm_db"
    "/var/www/warehouse.mohjss.so:mohjss_db"
    "/var/www/wrbrmoh.vistawims.com:wrbrmoh_db"
    "/var/www/wrslmoh.vistawims.com:wrslmoh_db"
)

DB_ONLY=false
FILES_ONLY=false
DRY_RUN=false

for arg in "$@"; do
    case $arg in
        --db-only)    DB_ONLY=true ;;
        --files-only) FILES_ONLY=true ;;
        --dry-run)    DRY_RUN=true ;;
    esac
done

# Default: do both db and files
if [ "$DB_ONLY" = true ]; then
    DO_DB=true
    DO_FILES=false
elif [ "$FILES_ONLY" = true ]; then
    DO_DB=false
    DO_FILES=true
else
    DO_DB=true
    DO_FILES=true
fi

# Load DB config from source .env
load_db_config() {
    local env_file="$1"
    if [ ! -f "$env_file" ]; then
        echo "ERROR: .env not found at $env_file"
        exit 1
    fi
    export DB_HOST=$(grep '^DB_HOST=' "$env_file" | cut -d= -f2-)
    export DB_PORT=$(grep '^DB_PORT=' "$env_file" | cut -d= -f2-)
    export DB_DATABASE=$(grep '^DB_DATABASE=' "$env_file" | cut -d= -f2-)
    export DB_USERNAME=$(grep '^DB_USERNAME=' "$env_file" | cut -d= -f2-)
    export DB_PASSWORD=$(grep '^DB_PASSWORD=' "$env_file" | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//")
}

echo "=== Warehouse Sync: warehouse.damalnugal.com -> others ==="
load_db_config "$SOURCE_ENV"
SOURCE_DB="$DB_DATABASE"

if [ "$DRY_RUN" = true ]; then
    echo "[DRY RUN] No changes will be made."
fi

# ---- Database dump and restore ----
if [ "$DO_DB" = true ]; then
    echo ""
    echo ">>> Dumping database: $SOURCE_DB"
    mkdir -p "$(dirname "$DUMP_FILE")"
    if [ "$DRY_RUN" = false ]; then
        MYSQL_PWD="$DB_PASSWORD" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
            --single-transaction --quick --lock-tables=false \
            "$SOURCE_DB" > "$DUMP_FILE"
        echo "Dump saved to: $DUMP_FILE"
    else
        echo "[DRY RUN] Would dump $SOURCE_DB to $DUMP_FILE"
    fi

    for target_spec in "${TARGETS[@]}"; do
        TARGET_DIR="${target_spec%%:*}"
        TARGET_DB="${target_spec##*:}"
        TARGET_ENV="${TARGET_DIR}/.env"

        echo ""
        echo ">>> Restoring to $TARGET_DB ($TARGET_DIR)"

        if [ ! -f "$TARGET_ENV" ]; then
            echo "WARN: Skipping $TARGET_DIR - no .env"
            continue
        fi

        if [ "$DRY_RUN" = false ]; then
            load_db_config "$TARGET_ENV"
            MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -e "DROP DATABASE IF EXISTS \`$TARGET_DB\`; CREATE DATABASE \`$TARGET_DB\`;"
            MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$TARGET_DB" < "$DUMP_FILE"
            echo "Restored to $TARGET_DB"
        else
            echo "[DRY RUN] Would restore $DUMP_FILE to $TARGET_DB"
        fi
    done

    if [ "$DRY_RUN" = false ]; then
        echo ""
        echo "Cleaning up dump file..."
        rm -f "$DUMP_FILE"
    fi
fi

# ---- File sync ----
if [ "$DO_FILES" = true ]; then
    echo ""
    echo ">>> Syncing files from $SOURCE_DIR to targets"
    echo "Excluding: .env, .env.*, node_modules, vendor, bootstrap/cache/*.php, storage/logs, .git"

    for target_spec in "${TARGETS[@]}"; do
        TARGET_DIR="${target_spec%%:*}"
        echo ""
        echo "Syncing to $TARGET_DIR"
        if [ "$DRY_RUN" = false ]; then
            rsync -av --delete \
                --exclude='.env' \
                --exclude='.env.*' \
                --exclude='node_modules' \
                --exclude='vendor' \
                --exclude='bootstrap/cache/config.php' \
                --exclude='bootstrap/cache/services.php' \
                --exclude='bootstrap/cache/packages.php' \
                --exclude='storage/logs/*' \
                --exclude='storage/framework/cache/*' \
                --exclude='storage/framework/sessions/*' \
                --exclude='storage/framework/views/*' \
                --exclude='.git' \
                --exclude='*.sql' \
                "$SOURCE_DIR/" "$TARGET_DIR/"
        else
            echo "[DRY RUN] Would rsync $SOURCE_DIR/ to $TARGET_DIR/"
        fi
    done
fi

# ---- Post-sync: composer install + clear caches on targets ----
if [ "$DO_DB" = true ] || [ "$DO_FILES" = true ]; then
    echo ""
    echo ">>> Running composer install and clearing caches on targets"
    for target_spec in "${TARGETS[@]}"; do
        TARGET_DIR="${target_spec%%:*}"
        if [ -f "${TARGET_DIR}/composer.json" ]; then
            echo "  $TARGET_DIR"
            if [ "$DRY_RUN" = false ]; then
                rm -f "$TARGET_DIR/bootstrap/cache/config.php" "$TARGET_DIR/bootstrap/cache/services.php" "$TARGET_DIR/bootstrap/cache/packages.php" 2>/dev/null
                (cd "$TARGET_DIR" && composer install --no-interaction --no-dev --optimize-autoloader 2>/dev/null) && echo "    composer OK" || echo "    composer install skipped"
                (cd "$TARGET_DIR" && php artisan config:clear && php artisan cache:clear && php artisan view:clear 2>/dev/null) && echo "    cache cleared" || echo "    (cache clear skipped)"
            fi
        fi
    done
fi

echo ""
echo "=== Sync complete ==="
