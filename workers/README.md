# Game Rounds Worker

This worker script manages automatic game rounds for Roulette and Crash games. It ensures all users see the same rounds and results, with provably fair outcomes.

## Setup

### Option 1: Cron Job (Recommended)

Add this to your crontab to run the worker every 2 seconds:

```bash
* * * * * /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 2; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 4; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 6; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 8; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 10; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 12; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 14; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 16; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 18; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 20; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 22; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 24; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 26; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
* * * * * sleep 28; /usr/bin/php /path/to/casino/workers/game_rounds_worker.php
```

Or use a simpler approach with a single cron job that runs every minute and processes multiple times:

```bash
* * * * * for i in {0..29}; do /usr/bin/php /path/to/casino/workers/game_rounds_worker.php; sleep 2; done
```

### Option 2: Background Process

Run as a background daemon:

```bash
nohup php workers/game_rounds_worker.php > /dev/null 2>&1 &
```

### Option 3: Systemd Service

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
