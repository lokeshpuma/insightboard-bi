#!/usr/bin/env bash
set -e

echo "=== Starting InsightBoard Services ==="

# 1. Check MySQL
if ! mysqladmin ping -u root --silent 2>/dev/null; then
    echo "Starting MySQL service via brew..."
    brew services start mysql
    sleep 2
fi

# 2. Start Python Analytics Microservice in background
echo "Starting Python Analytics Microservice on http://127.0.0.1:8001..."
(
    cd python-analytics
    ./venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8001
) &
ANALYTICS_PID=$!

# 3. Start Queue Worker in background
echo "Starting Laravel Queue Worker..."
php artisan queue:work --sleep=2 --tries=3 &
QUEUE_PID=$!

# Clean up background jobs on exit
cleanup() {
    echo ""
    echo "Stopping InsightBoard background services..."
    kill $ANALYTICS_PID $QUEUE_PID 2>/dev/null || true
    exit 0
}
trap cleanup SIGINT SIGTERM EXIT

# 4. Start Laravel Web Server
echo "Starting Laravel Web Server on http://localhost:8000..."
echo "Open your browser at: http://localhost:8000"
echo "Press Ctrl+C to stop all services."
php artisan serve --port=8000

