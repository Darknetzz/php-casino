# Game Rounds Worker

This worker script manages automatic game rounds for Roulette and Crash games. It ensures all users see the same rounds and results, with provably fair outcomes.

## Setup

### Option 1: Cron Job

**Note:** Cron has a minimum granularity of 1 minute. To run the worker more frequently, use a single cron job with a loop:

```bash
* * * * * for i in {0..29}; do /usr/bin/php /path/to/casino/workers/game_rounds_worker.php; sleep 2; done
```

Replace `/path/to/casino` with your actual path (e.g., `/home/kriss/web/html/casino`).

**⚠️ Do NOT add multiple separate cron entries** - use the single loop approach above, or prefer Option 2 or 3 below.

### Option 2: Background Process (Recommended for most users)

Run as a background daemon using the management script:

```bash
cd /path/to/casino
chmod +x workers/manage_worker.sh
./workers/manage_worker.sh start
```

Or manually:

```bash
nohup php workers/game_rounds_worker.php > /dev/null 2>&1 &
```

The management script provides easy control:
- `./workers/manage_worker.sh start` - Start the worker
- `./workers/manage_worker.sh stop` - Stop the worker
- `./workers/manage_worker.sh restart` - Restart the worker
- `./workers/manage_worker.sh status` - Check if worker is running

### Option 3: Systemd Service (Best for production servers)

Create `/etc/systemd/system/casino-worker.service`:

```ini
[Unit]
Description=Casino Game Rounds Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/casino
ExecStart=/usr/bin/php /path/to/casino/workers/game_rounds_worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl enable casino-worker
sudo systemctl start casino-worker
```

## Configuration

Game round timings can be configured in the admin panel under Casino Settings:

- `roulette_betting_duration`: How long betting is open (default: 15 seconds)
- `roulette_spinning_duration`: How long the wheel spins (default: 4 seconds)
- `roulette_round_interval`: Time between rounds (default: 5 seconds)
- `crash_betting_duration`: How long betting is open (default: 15 seconds)
- `crash_round_interval`: Time between rounds (default: 5 seconds)

## How It Works

1. **Betting Phase**: Users can place bets during the betting window
2. **Game Phase**: The round executes (roulette spins, crash multiplier rises)
3. **Result Phase**: Results are calculated using provably fair system
4. **Payout Phase**: Winnings are automatically distributed

## Provably Fair System

- Server generates a seed and its hash before each round
- Hash is shown to users (they can verify fairness after the round)
- Results are deterministically generated from the seed
- Admins can predict results by knowing the server seed

## Troubleshooting

- Check that the worker has write access to the database
- Ensure PHP has proper permissions
- Check logs for any errors
- Verify the database tables were created correctly
