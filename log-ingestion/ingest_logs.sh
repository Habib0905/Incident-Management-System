#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Load .env if exists
if [ -f "$SCRIPT_DIR/.env" ]; then
    set -a
    source "$SCRIPT_DIR/.env"
    set +a
fi

API_URL="${API_URL:-http://localhost:8000/api}"
LOG_FILE="${LOG_FILE:-storage/logs/sample/production.log}"
SERVER_NAME="${SERVER_NAME:-Production Web Server}"
CHECKPOINT_FILE="${CHECKPOINT_FILE:-.ingest_offset}"
POLL_INTERVAL="${POLL_INTERVAL:-2}"

PID_FILE="${PID_FILE:-.ingest_logs.pid}"

usage() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  LOG_FILE=/path/to/logfile     Log file to watch (default: storage/logs/sample/production.log)"
    echo "  SERVER_NAME='Server Name'     Server name in database (default: Production Web Server)"
    echo "  CHECKPOINT_FILE=/path         Checkpoint file (default: .ingest_offset)"
    echo "  POLL_INTERVAL=2               Seconds between checks (default: 2)"
    echo ""
    echo "Commands:"
    echo "  start                         Start ingestion in background"
    echo "  stop                          Stop running ingestion"
    echo "  status                        Check if running"
    echo "  once                          Run once (no loop)"
    echo ""
    echo "Examples:"
    echo "  $0 start"
    echo "  LOG_FILE=/var/log/app.log SERVER_NAME='Web Server' $0 start"
    echo "  $0 once"
}

get_server_api_key() {
    local name="$1"
    cd "$SCRIPT_DIR"
    php artisan tinker --execute="echo App\\Models\\Server::where('name', 'like', '%$name%')->first()?->api_key;" 2>/dev/null
}

load_checkpoint() {
    if [ -f "$CHECKPOINT_FILE" ]; then
        cat "$CHECKPOINT_FILE"
    else
        echo "0"
    fi
}

save_checkpoint() {
    echo "$1" > "$CHECKPOINT_FILE"
}

parse_and_send_logs() {
    local file="$1"
    local offset="$2"
    
    python3 << PYTHON_SCRIPT
import re
import json
import subprocess

log_file = "$file"
start_offset = int("$offset")
api_url = "$API_URL"
server_api_key = "$SERVER_API_KEY"

with open(log_file, 'r') as f:
    f.seek(start_offset)
    new_content = f.read()
    current_pos = f.tell()

if not new_content.strip():
    print("No new logs to process")
    print(f"CHECKPOINT:{current_pos}")
    exit(0)

lines = new_content.split('\n')

pattern = re.compile(r'^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\s*\[(\w+)\]\s*\[(\w+)\]\s+(.+)$')

for line in lines:
    line = line.strip()
    if not line:
        continue
    
    match = pattern.match(line)
    if match:
        timestamp, level, source, message = match.groups()
    else:
        timestamp = None
        level = 'info'
        source = 'unknown'
        message = line
    
    payload = {
        'timestamp': timestamp,
        'level': level,
        'source': source,
        'message': message
    }
    
    result = subprocess.run(
        ['curl', '-s', '-w', '\\n%{http_code}', '-X', 'POST', f'{api_url}/logs',
         '-H', 'Content-Type: application/json',
         '-H', f'Authorization: Bearer {server_api_key}',
         '-d', json.dumps(payload)],
        capture_output=True,
        text=True
    )
    
    response = result.stdout.strip()
    http_code = response.split('\\n')[-1] if response else '000'
    
    if http_code == '201':
        print(f'[{level.upper()}] Sent: {message[:60]}')
    else:
        print(f'[{level.upper()}] Failed: {message[:60]} ({http_code})')

print(f"CHECKPOINT:{current_pos}")
PYTHON_SCRIPT
}

ingest_once() {
    local current_size
    local last_offset
    
    current_size=$(stat -c%s "$LOG_FILE" 2>/dev/null)
    
    if [ -z "$current_size" ]; then
        echo "Error: Cannot read log file: $LOG_FILE"
        return 1
    fi
    
    last_offset=$(load_checkpoint)
    
    if [ "$current_size" -le "$last_offset" ]; then
        echo "No new logs (file size: $current_size, offset: $last_offset)"
        return 0
    fi
    
    echo "Processing new logs from offset $last_offset to $current_size..."
    
    local output
    output=$(parse_and_send_logs "$LOG_FILE" "$last_offset")
    echo "$output" | grep -v "^CHECKPOINT:"
    
    local saved_offset
    saved_offset=$(echo "$output" | grep "^CHECKPOINT:" | cut -d: -f2)
    if [ -n "$saved_offset" ]; then
        save_checkpoint "$saved_offset"
    fi
}

ingest_loop() {
    echo "Starting log ingestion..."
    echo "Watching: $LOG_FILE"
    echo "Server: $SERVER_NAME"
    echo "Poll interval: ${POLL_INTERVAL}s"
    echo "---"
    
    while true; do
        ingest_once
        sleep "$POLL_INTERVAL"
    done
}

start_daemon() {
    if [ -f "$PID_FILE" ]; then
        existing_pid=$(cat "$PID_FILE")
        if kill -0 "$existing_pid" 2>/dev/null; then
            echo "Already running with PID $existing_pid"
            return 1
        fi
        rm -f "$PID_FILE"
    fi
    
    (
        ingest_loop >> .ingest_logs.log 2>&1
    ) &
    BG_PID=$!
    sleep 1
    echo "$BG_PID" > "$PID_FILE"
    
    echo "Started with PID $BG_PID"
    echo "Log file: .ingest_logs.log"
}

stop_daemon() {
    if [ -f "$PID_FILE" ]; then
        pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid"
            echo "Stopped PID $pid"
            rm -f "$PID_FILE"
        else
            rm -f "$PID_FILE"
            echo "Process not running"
        fi
    else
        echo "PID file not found"
    fi
}

check_status() {
    if [ -f "$PID_FILE" ]; then
        pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "Running with PID $pid"
            return 0
        else
            echo "Not running (stale PID file)"
            return 1
        fi
    else
        echo "Not running"
        return 1
    fi
}

if [ -z "$SERVER_API_KEY" ]; then
    SERVER_API_KEY=$(get_server_api_key "$SERVER_NAME")
fi

if [ -z "$SERVER_API_KEY" ]; then
    echo "Error: Server not found: $SERVER_NAME"
    exit 1
fi

COMMAND="${1:-once}"

case "$COMMAND" in
    start)
        start_daemon
        ;;
    stop)
        stop_daemon
        ;;
    status)
        check_status
        ;;
    once)
        ingest_once
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        echo "Unknown command: $COMMAND"
        usage
        exit 1
        ;;
esac
