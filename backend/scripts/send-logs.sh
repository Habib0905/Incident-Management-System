#!/bin/bash

API_URL="${API_URL:-http://localhost:8000/api}"
SERVER_API_KEY="${SERVER_API_KEY:-sk_demo_server_123456789012345678901234567890123456789}"
LOG_FILE="${LOG_FILE:-/var/log/syslog}"
BATCH_SIZE=10
FOLLOW=false

print_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -k, --api-key KEY     Server API key (required)"
    echo "  -u, --url URL         API URL (default: http://localhost:8000/api)"
    echo "  -f, --file FILE       Log file to tail (default: /var/log/syslog)"
    echo "  -t, --tail            Follow mode (like tail -f)"
    echo "  -b, --batch SIZE      Batch size for sending logs"
    echo "  -h, --help           Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 -k sk_your_key -f /var/log/custom.log"
    echo "  $0 --tail -f /var/log/nginx/access.log"
    exit 0
}

while [[ $# -gt 0 ]]; do
    case $1 in
        -k|--api-key)
            SERVER_API_KEY="$2"
            shift 2
            ;;
        -u|--url)
            API_URL="$2"
            shift 2
            ;;
        -f|--file)
            LOG_FILE="$2"
            shift 2
            ;;
        -t|--tail)
            FOLLOW=true
            shift
            ;;
        -b|--batch)
            BATCH_SIZE="$2"
            shift 2
            ;;
        -h|--help)
            print_usage
            ;;
        *)
            echo "Unknown option: $1"
            print_usage
            ;;
    esac
done

if [ ! -f "$LOG_FILE" ]; then
    echo "Error: Log file not found: $LOG_FILE"
    exit 1
fi

send_json_log() {
    local message="$1"
    local level="$2"
    local source="$3"
    
    local payload=$(cat <<EOF
{
    "message": "$message",
    "level": "$level",
    "source": "$source",
    "timestamp": "$(date -Iseconds)"
}
EOF
)
    
    response=$(curl -s -X POST "$API_URL/logs" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $SERVER_API_KEY" \
        -d "$payload")
    
    if echo "$response" | grep -q "log"; then
        echo "[SENT] $message"
    else
        echo "[ERROR] $response"
    fi
}

send_text_log() {
    local message="$1"
    
    response=$(curl -s -X POST "$API_URL/logs" \
        -H "Content-Type: text/plain" \
        -H "Authorization: Bearer $SERVER_API_KEY" \
        -d "$message")
    
    if echo "$response" | grep -q "log"; then
        echo "[SENT] $message"
    else
        echo "[ERROR] $response"
    fi
}

if [ "$FOLLOW" = true ]; then
    echo "Tailing log file: $LOG_FILE (Press Ctrl+C to stop)"
    tail -f "$LOG_FILE" | while read line; do
        send_text_log "$line"
    done
else
    echo "Sending logs from: $LOG_FILE"
    count=0
    tail -n 100 "$LOG_FILE" | while read line; do
        if [ -n "$line" ]; then
            send_text_log "$line"
            count=$((count + 1))
            if [ $count -ge $BATCH_SIZE ]; then
                break
            fi
        fi
    done
    echo "Done. Sent $count logs."
fi