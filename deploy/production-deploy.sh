#!/bin/bash

# Production Deployment Script for Helpdesk System
# Version: 1.0
# Phase 4 Implementation

set -e  # Exit on error

LOG_FILE="/var/log/helpdesk-deploy.log"
DEPLOY_DIR="/var/www/helpdesk"
BACKUP_DIR="/var/backups/helpdesk"
DATE=$(date +"%Y%m%d_%H%M%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

error() {
    log "${RED}ERROR: $1${NC}"
    exit 1
}

success() {
    log "${GREEN}SUCCESS: $1${NC}"
}

warning() {
    log "${YELLOW}WARNING: $1${NC}"
}

# Check if running as correct user
if [ "$EUID" -eq 0 ]; then
    error "Do not run this script as root. Use www-data or deployment user."
fi

log "Starting production deployment..."

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Step 1: Create database backup
log "Creating database backup..."
BACKUP_FILE="$BACKUP_DIR/helpdesk_backup_$DATE.sql"
mysqldump helpdesk > "$BACKUP_FILE" || error "Database backup failed"
success "Database backup created: $BACKUP_FILE"

# Step 2: Put application in maintenance mode
log "Enabling maintenance mode..."
cd "$DEPLOY_DIR"
php artisan down --render="errors::503" --retry=60 || error "Failed to enable maintenance mode"

# Step 3: Pull latest code
log "Pulling latest code from repository..."
git fetch origin || error "Git fetch failed"
git reset --hard origin/main || error "Git reset failed"
success "Code updated successfully"

# Step 4: Install dependencies
log "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction || error "Composer install failed"
success "Dependencies installed"

# Step 5: Run database migrations
log "Running database migrations..."
php artisan migrate --force || error "Database migration failed"
success "Database migrations completed"

# Step 6: Seed helpdesk data if needed
log "Seeding helpdesk data..."
php artisan db:seed --class=HelpdeskSeeder --force || warning "Seeding failed or already completed"

# Step 7: Clear and rebuild caches
log "Clearing and rebuilding caches..."
php artisan config:clear || error "Config clear failed"
php artisan route:clear || error "Route clear failed"
php artisan view:clear || error "View clear failed"
php artisan cache:clear || error "Cache clear failed"

php artisan config:cache || error "Config cache failed"
php artisan route:cache || error "Route cache failed"
php artisan view:cache || error "View cache failed"
success "Caches rebuilt successfully"

# Step 8: Build frontend assets
log "Building frontend assets..."
npm ci --production || error "NPM install failed"
npm run build || error "Asset build failed"
success "Frontend assets built"

# Step 9: Restart queue workers
log "Restarting queue workers..."
sudo supervisorctl restart helpdesk:* || error "Failed to restart queue workers"
success "Queue workers restarted"

# Step 10: Optimize application
log "Optimizing application..."
php artisan optimize || error "Application optimization failed"

# Step 11: Set proper permissions
log "Setting file permissions..."
sudo chown -R www-data:www-data "$DEPLOY_DIR"
sudo chmod -R 755 "$DEPLOY_DIR"
sudo chmod -R 775 "$DEPLOY_DIR/storage"
sudo chmod -R 775 "$DEPLOY_DIR/bootstrap/cache"
success "Permissions set"

# Step 12: Disable maintenance mode
log "Disabling maintenance mode..."
php artisan up || error "Failed to disable maintenance mode"
success "Application is back online"

# Step 13: Run health checks
log "Running health checks..."
php artisan health:check || warning "Health check warnings detected"

# Step 14: Clean up old backups (keep last 7 days)
log "Cleaning up old backups..."
find "$BACKUP_DIR" -name "helpdesk_backup_*.sql" -mtime +7 -delete || warning "Backup cleanup failed"

log "Deployment completed successfully!"
log "Backup location: $BACKUP_FILE"
log "Application is now running the latest version."

# Send deployment notification (if Slack webhook is configured)
if [ ! -z "$SLACK_WEBHOOK_URL" ]; then
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"🚀 Helpdesk deployment completed successfully at $(date)\"}" \
        "$SLACK_WEBHOOK_URL" || warning "Slack notification failed"
fi

exit 0
