# PHP Casino Web Application

A fun, fake-money casino web application built with PHP, JavaScript/jQuery, and SQLite. Play slots, blackjack, and roulette with pretend money!

## Features

- 🔐 **User Authentication**
  - Sign up with username, email, and password
  - Secure password hashing using PHP's `password_hash()`
  - Session-based authentication

- 🎰 **Casino Games**
  - **Slots**: 3-reel slot machine with multiple symbol combinations
  - **Blackjack**: Full blackjack game with hit/stand functionality
  - **Roulette**: Bet on colors, numbers, or ranges

- 💰 **Balance Management**
  - Starting balance: $1,000 (fake money, configurable)
  - Real-time balance updates
  - Transaction history tracking
  - Automatic balance updates during gameplay
  - Profile page to refill balance (with max deposit limit)

- ⚙️ **Admin Panel**
  - Manage casino settings (max deposit, max bet, starting balance)
  - User administration (view all users, edit balances, toggle admin status)
  - Delete users
  - Accessible only to admin users

- 🎨 **Modern UI**
  - Responsive design
  - Beautiful gradient backgrounds
  - Smooth animations
  - Game instructions included

## Requirements

### For Docker:
- Docker and Docker Compose

### For Manual Installation:
- PHP 7.4 or higher
- PHP PDO SQLite extension
- Web server (Apache, Nginx, or PHP built-in server)
- jQuery (loaded via CDN)

## Installation

### Option 1: Docker (Recommended)

1. **Clone or download the repository**
   ```bash
   git clone https://github.com/Darknetzz/php-casino.git
   cd php-casino
   ```

2. **Build and run with Docker Compose**
   ```bash
   docker-compose up -d
   ```

3. **Access the application**
   
   Open your browser and navigate to:
   - `http://localhost:8080`

4. **Create an account**
   
   Visit the sign-up page and create your account. You'll start with $1,000 in fake money!

**Docker Commands:**
- Start: `docker-compose up -d`
- Stop: `docker-compose down`
- View logs: `docker-compose logs -f`
- Rebuild: `docker-compose up -d --build`

**Note:** The database is persisted in the `./data` directory, so your data will be preserved when restarting the container.

### Option 2: Manual Installation

1. **Clone or download the repository**
   ```bash
   git clone https://github.com/Darknetzz/php-casino.git
   cd php-casino
   ```

2. **Set up the web server**
   
   If using Apache/Nginx, point your document root to the project directory.
   
   Or use PHP's built-in server for testing:
   ```bash
   php -S localhost:8000
   ```

3. **Set up database permissions**
   
   The application needs write permissions to create the SQLite database. Run:
   ```bash
   sudo mkdir -p data
   sudo chown www-data:www-data data  # or apache:apache, nginx:nginx, etc.
   sudo chmod 755 data
   ```
   
   Or run the setup script:
   ```bash
   php setup.php
   ```

4. **Access the application**
   
   Open your browser and navigate to:
   - `http://localhost:8000` (if using PHP built-in server)
   - Or your configured web server URL

5. **Create an account**
   
   Visit the sign-up page and create your account. You'll start with $1,000 in fake money!

## Project Structure

```
php-casino/
├── api/
│   └── api.php              # API endpoints for balance and transactions
├── data/                    # Database directory (auto-created, gitignored)
│   └── casino.db           # SQLite database (auto-created)
├── games/
│   ├── slots.php           # Slots game page
│   ├── blackjack.php       # Blackjack game page
│   └── roulette.php        # Roulette game page
├── includes/
│   ├── config.php          # Configuration and session management
│   └── database.php        # Database class and SQLite setup
├── js/
│   ├── auth.js             # Form validation
│   ├── slots.js            # Slots game logic
│   ├── blackjack.js        # Blackjack game logic
│   └── roulette.js         # Roulette game logic
├── pages/
│   ├── login.php           # Sign in page
│   ├── signup.php          # Sign up page
│   ├── logout.php          # Logout handler
│   ├── profile.php         # User profile and balance refill
│   └── admin.php           # Admin panel
├── index.php               # Main dashboard
├── setup.php               # Setup script for permissions
├── make_admin.php          # Utility to make a user admin
├── style.css               # Main stylesheet
└── README.md               # This file
```

## Game Rules

### Slots
- Match 3 symbols to win
- Multipliers:
  - 🍒🍒🍒 = 2x bet
  - 🍋🍋🍋 = 3x bet
  - 🍊🍊🍊 = 4x bet
  - 🍇🍇🍇 = 5x bet
  - 🎰🎰🎰 = 10x bet

### Blackjack
- Get as close to 21 as possible without going over
- Face cards (J, Q, K) = 10
- Aces = 1 or 11 (whichever is better)
- Beat the dealer to win 2x your bet
- Blackjack (21 with first 2 cards) = 2.5x payout

### Roulette
- Red/Black/Even/Odd/Low/High = 2x payout
- Green (0) = 14x payout
- Specific number = 36x payout

## Security Features

- Passwords are hashed using PHP's `password_hash()` (bcrypt)
- SQL injection protection via prepared statements
- XSS protection with `htmlspecialchars()`
- Session-based authentication
- Database files protected from direct web access

## Database Schema

### Users Table
- `id` - Primary key
- `username` - Unique username
- `email` - Unique email
- `password` - Hashed password
- `balance` - Current balance (default: 1000.00, configurable)
- `is_admin` - Admin flag (0 = regular user, 1 = admin)
- `created_at` - Account creation timestamp

### Transactions Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `type` - Transaction type (bet, win, deposit, admin)
- `amount` - Transaction amount
- `description` - Transaction description
- `created_at` - Transaction timestamp

### Settings Table
- `id` - Primary key
- `setting_key` - Setting name (e.g., 'max_deposit', 'max_bet', 'starting_balance')
- `setting_value` - Setting value
- `updated_at` - Last update timestamp
- `description` - Transaction description
- `created_at` - Transaction timestamp

## Troubleshooting

### Database Permission Errors

If you see "unable to open database file" errors:

1. Check that the `data/` directory exists and is writable:
   ```bash
   ls -la data/
   ```

2. Set proper permissions:
   ```bash
   sudo chown www-data:www-data data
   sudo chmod 755 data
   ```

3. Or run the setup script:
   ```bash
   php setup.php
   ```

### Session Issues

If sessions aren't working, ensure:
- PHP sessions are enabled
- The session directory is writable
- Cookies are enabled in your browser

## Development

### Adding New Games

1. Create a new PHP file in `games/`
2. Create corresponding JavaScript in `js/`
3. Add game card to `index.php`
4. Use the API endpoints in `api/api.php` for balance updates

### API Endpoints

- `GET api/api.php?action=getBalance` - Get current user balance
- `POST api/api.php?action=updateBalance` - Update balance (with amount, type, description)
- `GET api/api.php?action=getTransactions` - Get transaction history

## License

This project is open source and available for educational purposes.

## Contributing

Feel free to fork, modify, and use this project for learning or fun!

## Disclaimer

This is a **fake money** casino application for entertainment purposes only. No real money is involved.
