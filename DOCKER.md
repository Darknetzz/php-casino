# Docker Setup Guide

This guide explains how to run the Casino application using Docker.

## Quick Start

The Docker setup automatically pulls the application from the GitHub repository using HTTPS.

### Build and Run

1. **Build and start the container:**
   ```bash
   docker-compose up -d --build
   ```

2. **Access the application:**
   Open your browser and navigate to `http://localhost:8080`

3. **Stop the container:**
   ```bash
   docker-compose down
   ```

## Docker Commands

### Basic Operations
- **Start containers:** `docker-compose up -d`
- **Stop containers:** `docker-compose down`
- **View logs:** `docker-compose logs -f`
- **Rebuild (pulls latest from git):** `docker-compose up -d --build`
- **Restart:** `docker-compose restart`

### Container Management
- **View running containers:** `docker ps`
- **Execute commands in container:** `docker exec -it casino-app bash`
- **View container logs:** `docker logs casino-app -f`

## Git Repository Configuration

The Docker setup pulls the application directly from the GitHub repository using HTTPS:
- **Repository:** `https://github.com/Darknetzz/php-casino.git`
- **Default Branch:** `main`

### Changing the Repository

Edit `docker-compose.yml` to change the repository URL or branch:

```yaml
build:
  args:
    GIT_REPO: https://github.com/your-username/your-repo.git
    GIT_BRANCH: main
```

### Using SSH Instead of HTTPS

If you need SSH access (for private repositories):

1. Edit `docker-compose.yml` and change:
   ```yaml
   build:
     context: .
     dockerfile: Dockerfile.ssh  # Use SSH Dockerfile
     ssh:
       - default
     args:
       GIT_REPO: git@github.com:Darknetzz/php-casino.git
       GIT_BRANCH: main
   ```

2. Ensure SSH agent is running and build:
   ```bash
   eval $(ssh-agent)
   ssh-add ~/.ssh/id_rsa
   DOCKER_BUILDKIT=1 docker-compose -f docker-compose.ssh.yml up -d --build
   ```

## Data Persistence

The database and application data are stored in the `./data` directory on your host machine. This means:
- Your data persists even when containers are stopped
- You can backup the `./data` directory to save your database
- The database file is `./data/casino.db`

## Port Configuration

By default, the application runs on port `8080`. To change this, edit `docker-compose.yml`:

```yaml
ports:
  - "YOUR_PORT:80"  # Change YOUR_PORT to your desired port
```

## Development Mode

For development, you can mount the source code as a volume to see changes without rebuilding:

1. Edit `docker-compose.yml` and uncomment the volume mount:
   ```yaml
   volumes:
     - ./data:/var/www/html/data
     - .:/var/www/html  # Uncomment this line
   ```

2. Rebuild and restart:
   ```bash
   docker-compose up -d --build
   ```

**Note:** In production, remove the source code volume mount for better security.

## Worker Service (Optional)

The application includes a worker service for managing game rounds. To enable it:

1. Edit `docker-compose.yml` and uncomment the `casino-worker` service
2. Update it to use the SSH Dockerfile:
   ```yaml
   casino-worker:
     build:
       context: .
       dockerfile: Dockerfile.ssh
       ssh:
         - default
     container_name: casino-worker
     command: php /var/www/html/workers/game_rounds_worker.php
     volumes:
       - ./data:/var/www/html/data
     depends_on:
       - casino
     restart: unless-stopped
   ```
3. Restart: `docker-compose up -d`

The worker manages synchronized rounds for roulette and crash games.

## Troubleshooting

### Container won't start
- Check logs: `docker-compose logs`
- Ensure port 8080 is not in use: `lsof -i :8080`
- Try rebuilding: `docker-compose up -d --build`


### Database permission errors
- The container automatically sets correct permissions
- If issues persist, check: `docker exec -it casino-app ls -la /var/www/html/data`

### Can't access the application
- Verify container is running: `docker ps`
- Check if port is correct: `docker-compose ps`
- View Apache logs: `docker exec -it casino-app tail -f /var/log/apache2/error.log`

## Production Deployment

For production:

1. **Remove development volume mounts** from `docker-compose.yml`
2. **Use environment variables** for sensitive configuration
3. **Set up SSL/TLS** using a reverse proxy (nginx, traefik, etc.)
4. **Configure backups** for the `./data` directory
5. **Use Docker secrets** for sensitive data
6. **Set resource limits** in `docker-compose.yml`:
   ```yaml
   deploy:
     resources:
       limits:
         cpus: '1'
         memory: 512M
   ```

## Health Checks

The container includes a health check that verifies the application is responding. Check health status:

```bash
docker inspect --format='{{.State.Health.Status}}' casino-app
```

## Backup and Restore

### Backup
```bash
# Stop the container
docker-compose down

# Backup the data directory
tar -czf casino-backup-$(date +%Y%m%d).tar.gz data/

# Start the container
docker-compose up -d
```

### Restore
```bash
# Stop the container
docker-compose down

# Restore from backup
tar -xzf casino-backup-YYYYMMDD.tar.gz

# Start the container
docker-compose up -d
```
