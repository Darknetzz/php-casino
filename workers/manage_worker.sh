#!/bin/bash

# Worker Management Script
# Usage: ./manage_worker.sh [start|stop|restart|status]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKER_SCRIPT="$SCRIPT_DIR/game_rounds_worker.php"
PID_FILE="$SCRIPT_DIR/worker.pid"
LOG_FILE="$SCRIPT_DIR/worker.log"

# Get PHP path
PHP_BIN=$(which php)
if [ -z "$PHP_BIN" ]; then
    PHP_BIN="/usr/bin/php"
fi

# Function to get PID from PID file or process list
get_pid() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        # Verify the process is still running and is the worker
        if ps -p "$PID" > /dev/null 2>&1; then
            CMD=$(ps -p "$PID" -o cmd= 2>/dev/null | grep -q "game_rounds_worker.php" && echo "match")
            if [ -n "$CMD" ]; then
                echo "$PID"
                return 0
            fi
        fi
        # PID file exists but process is dead, remove stale file
        rm -f "$PID_FILE"
    fi
    
    # Try to find process by command
    PID=$(ps aux | grep "[p]hp.*game_rounds_worker.php" | awk '{print $2}' | head -n 1)
    if [ -n "$PID" ]; then
        echo "$PID"
        return 0
    fi
    
    return 1
}

start_worker() {
    PID=$(get_pid)
    if [ -n "$PID" ]; then
        echo "Worker is already running (PID: $PID)"
        return 1
    fi
    
    echo "Starting worker..."
    nohup "$PHP_BIN" "$WORKER_SCRIPT" > "$LOG_FILE" 2>&1 &
    NEW_PID=$!
    echo $NEW_PID > "$PID_FILE"
    
    # Wait a moment to check if it started successfully
    sleep 1
    if ps -p "$NEW_PID" > /dev/null 2>&1; then
        echo "Worker started successfully (PID: $NEW_PID)"
        return 0
    else
        echo "Failed to start worker. Check $LOG_FILE for errors."
        rm -f "$PID_FILE"
        return 1
    fi
}

stop_worker() {
    PID=$(get_pid)
    if [ -z "$PID" ]; then
        echo "Worker is not running"
        return 1
    fi
    
    echo "Stopping worker (PID: $PID)..."
    kill "$PID" 2>/dev/null
    
    # Wait for process to terminate
    for i in {1..10}; do
        if ! ps -p "$PID" > /dev/null 2>&1; then
            rm -f "$PID_FILE"
            echo "Worker stopped successfully"
            return 0
        fi
        sleep 1
    done
    
    # Force kill if still running
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "Force killing worker..."
        kill -9 "$PID" 2>/dev/null
        sleep 1
        rm -f "$PID_FILE"
        echo "Worker force stopped"
        return 0
    fi
}

restart_worker() {
    stop_worker
    sleep 2
    start_worker
}

status_worker() {
    PID=$(get_pid)
    if [ -z "$PID" ]; then
        echo "Worker is not running"
        return 1
    fi
    
    echo "Worker is running (PID: $PID)"
    
    # Show process info
    ps -p "$PID" -o pid,user,time,cmd | tail -n 1
    
    # Show last few lines of log if available
    if [ -f "$LOG_FILE" ]; then
        echo ""
        echo "Recent log entries:"
        tail -n 5 "$LOG_FILE" | sed 's/^/  /'
    fi
    
    return 0
}

# Main script logic
case "${1:-status}" in
    start)
        start_worker
        ;;
    stop)
        stop_worker
        ;;
    restart)
        restart_worker
        ;;
    status)
        status_worker
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac

exit $?
