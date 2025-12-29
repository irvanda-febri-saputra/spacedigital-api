#!/bin/bash
# =============================================================================
# SpaceDigital Dashboard - Docker Deployment Script
# =============================================================================
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# =============================================================================
# Check requirements
# =============================================================================
check_requirements() {
    print_status "Checking requirements..."
    
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    print_success "All requirements met!"
}

# =============================================================================
# Setup environment
# =============================================================================
setup_env() {
    print_status "Setting up environment..."
    
    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            print_warning ".env file created from .env.example. Please edit it with your production values!"
            print_warning "Run: nano .env"
            exit 1
        else
            print_error ".env.example not found!"
            exit 1
        fi
    fi
    
    print_success "Environment is configured!"
}

# =============================================================================
# Build and start containers
# =============================================================================
deploy() {
    print_status "Building and starting containers..."
    
    # Pull latest images (redis and cloudflared only, mysql runs on host)
    docker-compose pull redis cloudflared || true
    
    # Build application images
    docker-compose build --no-cache app reverb
    
    # Start containers
    docker-compose up -d
    
    print_success "Containers are starting..."
    
    # Wait for containers to be healthy
    print_status "Waiting for services to be ready..."
    sleep 10
    
    # Show status
    docker-compose ps
}

# =============================================================================
# Run Laravel setup commands
# =============================================================================
laravel_setup() {
    print_status "Running Laravel setup commands..."
    
    # Wait for MySQL to be ready
    print_status "Waiting for MySQL..."
    sleep 15
    
    # Generate app key if not set
    docker-compose exec -T app php artisan key:generate --force || true
    
    # Run migrations
    docker-compose exec -T app php artisan migrate --force
    
    # Cache config
    docker-compose exec -T app php artisan config:cache
    docker-compose exec -T app php artisan route:cache
    docker-compose exec -T app php artisan view:cache
    
    # Set storage permissions
    docker-compose exec -T app chmod -R 775 storage bootstrap/cache
    docker-compose exec -T app chown -R www-data:www-data storage bootstrap/cache
    
    print_success "Laravel setup completed!"
}

# =============================================================================
# Show logs
# =============================================================================
show_logs() {
    docker-compose logs -f --tail=100
}

# =============================================================================
# Stop containers
# =============================================================================
stop() {
    print_status "Stopping containers..."
    docker-compose down
    print_success "Containers stopped!"
}

# =============================================================================
# Restart containers
# =============================================================================
restart() {
    print_status "Restarting containers..."
    docker-compose restart
    print_success "Containers restarted!"
}

# =============================================================================
# Show status
# =============================================================================
status() {
    docker-compose ps
    echo ""
    print_status "Container logs (last 10 lines):"
    docker-compose logs --tail=10
}

# =============================================================================
# Update application
# =============================================================================
update() {
    print_status "Updating application..."
    
    # Pull latest code (if using git)
    if [ -d .git ]; then
        git pull origin main
    fi
    
    # Rebuild containers
    docker-compose build --no-cache app reverb
    
    # Restart with new images
    docker-compose up -d
    
    # Run migrations
    docker-compose exec -T app php artisan migrate --force
    
    # Clear and rebuild cache
    docker-compose exec -T app php artisan config:cache
    docker-compose exec -T app php artisan route:cache
    docker-compose exec -T app php artisan view:cache
    
    print_success "Application updated!"
}

# =============================================================================
# Main
# =============================================================================
case "$1" in
    install|deploy)
        check_requirements
        setup_env
        deploy
        laravel_setup
        print_success "Deployment completed! 🚀"
        print_status "Check your application at your Cloudflare tunnel domain."
        ;;
    start)
        docker-compose up -d
        print_success "Containers started!"
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    logs)
        show_logs
        ;;
    status)
        status
        ;;
    update)
        update
        ;;
    shell)
        docker-compose exec app /bin/sh
        ;;
    artisan)
        shift
        docker-compose exec app php artisan "$@"
        ;;
    *)
        echo "SpaceDigital Dashboard - Docker Deployment Script"
        echo ""
        echo "Usage: $0 {command}"
        echo ""
        echo "Commands:"
        echo "  install, deploy  - First-time deployment (build + migrate + start)"
        echo "  start            - Start all containers"
        echo "  stop             - Stop all containers"
        echo "  restart          - Restart all containers"
        echo "  logs             - Show container logs"
        echo "  status           - Show container status"
        echo "  update           - Update application (pull, rebuild, migrate)"
        echo "  shell            - Open shell in app container"
        echo "  artisan [cmd]    - Run artisan command"
        echo ""
        exit 1
        ;;
esac
