#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
LOG_FILE="${LOG_FILE:-$SCRIPT_DIR/logs/sample/production.log}"

mkdir -p "$(dirname "$LOG_FILE")"

RED='\033[0;31m'
YELLOW='\033[0;33m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
BLUE='\033[0;34m'
NC='\033[0m'

trap 'echo -e "\n${CYAN}[SIM] Stopping simulator...${NC}"; exit 0' INT TERM

log() {
    local level="$1"
    local source="$2"
    local message="$3"
    local timestamp
    timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    echo "${timestamp} [${level}] [${source}] ${message}" >> "$LOG_FILE"

    case "$level" in
        INFO)    echo -e "${GREEN}[${level}]${NC} [${CYAN}${source}${NC}] ${message}" ;;
        WARN)    echo -e "${YELLOW}[${level}]${NC} [${CYAN}${source}${NC}] ${message}" ;;
        ERROR)   echo -e "${RED}[${level}]${NC} [${CYAN}${source}${NC}] ${message}" ;;
    esac
}

rand() {
    echo $(( RANDOM % $1 ))
}

pick() {
    local arr=("$@")
    echo "${arr[$(rand ${#arr[@]} )]}"
}

log_startup() {
    log "INFO" "system" "Application starting up (PID: $$)"
    sleep 0.2
    log "INFO" "database" "Connected to PostgreSQL primary (pool: 20, latency: 2ms)"
    sleep 0.2
    log "INFO" "redis" "Connected to cache-primary:6379 (db: 0)"
    sleep 0.2
    log "INFO" "queue" "RabbitMQ connection established on amqp://mq-primary:5672"
    sleep 0.2
    log "INFO" "system" "All workers started (web: 4, worker: 8, scheduler: 1)"
    sleep 0.2
}

log_normal() {
    local endpoints=("/api/v1/users" "/api/v1/orders" "/api/v1/products" "/api/v1/health" "/api/v1/auth/login" "/api/v1/search" "/api/v1/notifications")
    local methods=("GET" "POST" "GET" "GET" "POST" "GET" "GET")
    local codes=("200" "201" "200" "200" "200" "200" "200")
    local idx=$(rand ${#endpoints[@]})

    log "INFO" "api" "${methods[$idx]} ${endpoints[$idx]} - ${codes[$idx]} ($(rand 200 + 10)ms)"
    sleep 0.3
    log "INFO" "cache" "Cache hit for key: user_profile_$(rand 1000) (TTL: 300s)"
    sleep 0.3
    log "INFO" "system" "Health check passed - CPU: $(rand 30 + 10)%, MEM: $(rand 40 + 20)%"
    sleep 0.3
}

log_warnings() {
    local warnings=(
        "database|Slow query detected: SELECT * FROM orders WHERE status='pending' (took $(rand 3 + 2).$(rand 900 + 100)s)"
        "database|Connection pool utilization at $(( $(rand 30) + 70 ))% (14/20 connections in use)"
        "system|Memory usage elevated: $(( $(rand 20) + 75 ))% (threshold: 80%)"
        "system|Disk usage at $(( $(rand 10) + 85 ))% on /var/data"
        "cache|Cache miss rate increasing: $(( $(rand 20) + 30 ))% (normal: <10%)"
        "cache|Redis memory usage at $(( $(rand 30) + 60 ))% of maxmemory (2GB)"
        "queue|Queue depth growing: email-send has $(rand 500 + 100) pending jobs"
        "api|Response time degraded for /api/v1/search: $(rand 2000 + 1500)ms"
        "network|Intermittent latency spike to upstream 10.0.0.$(rand 10 + 1): $(rand 500 + 200)ms"
        "auth|Rate limit approaching for IP 192.168.1.$(rand 255): $(rand 80 + 50)/100 requests"
    )

    for i in $(seq 1 $(( $(rand 3) + 2 ))); do
        local entry=$(pick "${warnings[@]}")
        local src="${entry%%|*}"
        local msg="${entry#*|}"
        log "WARN" "$src" "$msg"
        sleep 0.3
    done
}

log_errors() {
    local error_pool=(
        "database|Connection pool exhausted - max connections reached (20/20)"
        "database|Deadlock detected on table 'orders' - transaction rolled back"
        "database|Replication lag exceeded 30s on replica db-replica-02"
        "system|OOM killer activated: process mysqld (PID $(rand 9000 + 1000)) killed"
        "system|Disk full on /var/log - no space left on device"
        "system|CPU spike detected: $(rand 40 + 60)% sustained for 60s"
        "network|Connection refused to upstream server 10.0.0.$(rand 10 + 1):8080"
        "network|DNS resolution failed for api.external-service.com"
        "auth|Brute force detected: $(rand 50 + 20) failed login attempts from 192.168.1.$(rand 255)"
        "auth|JWT token validation failed: signature mismatch for user session $(cat /proc/sys/kernel/random/uuid 2>/dev/null || echo "sess_$(rand 99999)")"
        "container|Container api-worker CrashLoopBackOff - restart count $(rand 5 + 3)"
        "container|Image pull failed for registry.internal.io/api:v2.$(rand 10): manifest not found"
        "cloud|EC2 instance i-0$(cat /proc/sys/kernel/random/uuid 2>/dev/null | head -c 12 || echo "$(rand 999999999999)") terminated unexpectedly"
        "cloud|S3 PutObject failed: AccessDenied on bucket prod-uploads"
        "nginx|upstream timed out (110: Connection timed out) while connecting to upstream 10.0.0.$(rand 10 + 1):8080"
        "nginx|no live upstreams while connecting to upstream backend_pool"
        "apache|Apache segfault in mod_ssl worker process (PID $(rand 9000 + 1000))"
        "apache|AH00124: Request exceeded the limit of 10 internal redirects"
        "api|502 Bad Gateway from payment-service endpoint /api/v1/charge"
        "api|503 Service Unavailable from inventory-service (circuit breaker open)"
        "queue|RabbitMQ connection lost - job email-send retry $(rand 3 + 1) of 5"
        "queue|Celery worker dead: task process_payment timed out after 300s"
        "file|Permission denied: cannot write to /var/www/uploads/avatar_$(rand 9999).jpg"
        "file|File upload failed: exceeded max size 50MB for /api/v1/documents"
        "email|SMTP connection rejected by mx.gmail.com: 550 Authentication required"
        "email|Email delivery failed: recipient bounced user$(rand 100)@domain.com"
        "cache|Redis connection timeout after 30s on host cache-primary:6379"
        "cache|Cache eviction spike: $(rand 1000 + 500) keys evicted in last 60s"
    )

    for i in $(seq 1 $(( $(rand 4) + 3 ))); do
        local entry=$(pick "${error_pool[@]}")
        local src="${entry%%|*}"
        local msg="${entry#*|}"
        log "ERROR" "$src" "$msg"
        sleep 0.2
    done
}

log_recovery() {
    log "INFO" "database" "Connection pool recovered (8/20 connections in use)"
    sleep 0.2
    log "INFO" "system" "Memory usage normalized: $(( $(rand 20) + 30 ))%"
    sleep 0.2
    log "INFO" "queue" "Queue drained: email-send has 0 pending jobs"
    sleep 0.2
    log "INFO" "cache" "Cache warmed up - hit rate restored to $(( $(rand 10) + 85 ))%"
    sleep 0.2
    log "INFO" "system" "All health checks passing"
    sleep 0.2
}

echo -e "${CYAN}============================================${NC}"
echo -e "${CYAN}  Real Application Log Simulator${NC}"
echo -e "${CYAN}============================================${NC}"
echo -e "${GREEN}  Writing to: ${LOG_FILE}${NC}"
echo -e "${YELLOW}  Press Ctrl+C to stop${NC}"
echo -e "${CYAN}============================================${NC}"
echo ""

cycle=0
while true; do
    cycle=$(( cycle + 1 ))
    echo -e "${BLUE}--- Cycle $cycle ---${NC}"

    log_startup
    log_normal
    log_warnings
    log_errors
    log_recovery

    echo -e "${BLUE}  Cycle complete. Repeating in 3s...${NC}"
    echo ""
    sleep 3
done
