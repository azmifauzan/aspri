# Docker Quick Reference - ASPRI

## Quick Start

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# View logs
docker-compose logs -f

# Restart specific service
docker-compose restart backend
```

## Docker Compose Commands

### Basic Operations
```bash
# Build images
docker-compose build

# Build without cache
docker-compose build --no-cache

# Start services
docker-compose up

# Start in background (detached)
docker-compose up -d

# Stop services
docker-compose down

# Stop and remove volumes (⚠️ DELETES DATA)
docker-compose down -v

# Restart services
docker-compose restart

# Restart specific service
docker-compose restart backend
docker-compose restart frontend
docker-compose restart postgres
```

### Viewing Logs
```bash
# All services
docker-compose logs

# Follow mode (live updates)
docker-compose logs -f

# Specific service
docker-compose logs backend
docker-compose logs -f frontend

# Last N lines
docker-compose logs --tail=100 backend
```

### Service Management
```bash
# List running services
docker-compose ps

# List all services (including stopped)
docker-compose ps -a

# Check service status
docker-compose ps

# Scale service (if supported)
docker-compose up -d --scale backend=2
```

### Build & Rebuild
```bash
# Rebuild specific service
docker-compose build backend

# Rebuild and restart
docker-compose up -d --build

# Force recreate containers
docker-compose up -d --force-recreate
```

## Docker Commands (Direct)

### Container Management
```bash
# List running containers
docker ps

# List all containers
docker ps -a

# Stop container
docker stop aspri-backend

# Start container
docker start aspri-backend

# Restart container
docker restart aspri-backend

# Remove container
docker rm aspri-backend

# Remove running container (force)
docker rm -f aspri-backend
```

### Image Management
```bash
# List images
docker images

# Remove image
docker rmi aspri-backend

# Remove unused images
docker image prune

# Remove all unused images
docker image prune -a
```

### Logs & Debugging
```bash
# View logs
docker logs aspri-backend

# Follow logs
docker logs -f aspri-backend

# Last N lines
docker logs --tail=100 aspri-backend

# With timestamps
docker logs -t aspri-backend
```

### Execute Commands in Container
```bash
# Open bash shell
docker exec -it aspri-backend bash

# Run single command
docker exec aspri-backend ls -la

# Run as specific user
docker exec -u postgres aspri-postgres psql -U postgres
```

### Inspect & Stats
```bash
# Inspect container
docker inspect aspri-backend

# Container stats (CPU, memory)
docker stats

# Stats for specific container
docker stats aspri-backend

# Network information
docker network ls
docker network inspect aspri-network
```

## Database Commands

### Access PostgreSQL
```bash
# Via docker exec
docker exec -it aspri-postgres psql -U postgres -d aspri

# Connect from host (if port exposed)
psql -h localhost -U postgres -d aspri
```

### PostgreSQL Commands (inside psql)
```sql
-- List databases
\l

-- List tables
\dt

-- Describe table
\d user_profiles

-- Quit
\q
```

### Database Operations
```bash
# Backup database
docker exec aspri-postgres pg_dump -U postgres aspri > backup.sql

# Restore database
docker exec -i aspri-postgres psql -U postgres aspri < backup.sql

# Copy file to container
docker cp backup.sql aspri-postgres:/tmp/backup.sql

# Copy file from container
docker cp aspri-postgres:/tmp/backup.sql ./backup.sql
```

## Volume Management

### List & Inspect
```bash
# List volumes
docker volume ls

# Inspect volume
docker volume inspect aspri_postgres_data

# Find volume location
docker volume inspect aspri_postgres_data | grep Mountpoint
```

### Backup & Restore
```bash
# Backup volume
docker run --rm \
  -v aspri_postgres_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/postgres_backup.tar.gz -C /data .

# Restore volume
docker run --rm \
  -v aspri_postgres_data:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd /data && tar xzf /backup/postgres_backup.tar.gz"
```

### Clean Up
```bash
# Remove unused volumes
docker volume prune

# Remove specific volume (⚠️ DELETES DATA)
docker volume rm aspri_postgres_data
```

## Network Management

```bash
# List networks
docker network ls

# Inspect network
docker network inspect aspri-network

# Create network
docker network create my-network

# Connect container to network
docker network connect aspri-network my-container

# Disconnect
docker network disconnect aspri-network my-container
```

## Troubleshooting Commands

### Check Resource Usage
```bash
# Disk usage
docker system df

# Detailed disk usage
docker system df -v

# Container resource usage
docker stats --no-stream
```

### Clean Up Resources
```bash
# Remove stopped containers
docker container prune

# Remove unused images
docker image prune

# Remove unused volumes
docker volume prune

# Remove unused networks
docker network prune

# Remove everything unused (⚠️ CAREFUL)
docker system prune

# Remove everything including volumes (⚠️ VERY CAREFUL)
docker system prune -a --volumes
```

### Debugging

```bash
# Check container health
docker inspect --format='{{.State.Health.Status}}' aspri-backend

# View container IP
docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' aspri-backend

# View environment variables
docker inspect --format='{{range .Config.Env}}{{println .}}{{end}}' aspri-backend

# Check port mappings
docker port aspri-backend
```

## ASPRI Specific Commands

### Complete Reset (Fresh Start)
```bash
# Stop everything
docker-compose down -v

# Remove images
docker rmi aspri-backend aspri-frontend

# Start fresh
docker-compose up -d --build
```

### Quick Restart After Code Changes
```bash
# Backend only
docker-compose build backend
docker-compose up -d backend

# Frontend only
docker-compose build frontend
docker-compose up -d frontend

# Both
docker-compose up -d --build
```

### View Application Logs
```bash
# All services, color-coded
docker-compose logs -f

# Backend only
docker-compose logs -f backend

# Frontend only
docker-compose logs -f frontend

# Database only
docker-compose logs -f postgres

# Filter by keyword
docker-compose logs | grep ERROR
docker-compose logs backend | grep "Started BackendApplication"
```

### Database Quick Access
```bash
# Open psql
docker exec -it aspri-postgres psql -U postgres -d aspri

# Run SQL query directly
docker exec aspri-postgres psql -U postgres -d aspri -c "SELECT * FROM user_profiles;"

# Check if migrations applied
docker exec aspri-postgres psql -U postgres -d aspri -c "SELECT * FROM flyway_schema_history;"
```

### Check Service Health
```bash
# Backend health
curl http://localhost:8080/api/health

# Frontend
curl -I http://localhost

# Database
docker exec aspri-postgres pg_isready -U postgres
```

## Development Workflow

### 1. Start Development
```bash
cp .env.example .env
# Edit .env
docker-compose up -d
docker-compose logs -f
```

### 2. Make Changes
```bash
# Edit code...
# Backend auto-restart (if devtools enabled) or:
docker-compose restart backend

# Frontend - rebuild:
docker-compose up -d --build frontend
```

### 3. View Logs
```bash
docker-compose logs -f backend
```

### 4. Test
```bash
# Backend API
curl http://localhost:8080/api/health

# Database
docker exec -it aspri-postgres psql -U postgres -d aspri
```

### 5. Stop
```bash
# Stop but keep data
docker-compose down

# Stop and remove data
docker-compose down -v
```

## Production Deployment

```bash
# 1. Build production images
docker-compose -f docker-compose.prod.yml build

# 2. Start with production config
docker-compose -f docker-compose.prod.yml up -d

# 3. Monitor logs
docker-compose -f docker-compose.prod.yml logs -f

# 4. Health check
docker-compose -f docker-compose.prod.yml ps
```

## Emergency Commands

### Service Not Starting
```bash
# Check logs
docker-compose logs backend

# Check last 50 lines
docker-compose logs --tail=50 backend

# Remove and recreate
docker-compose down
docker-compose up -d
```

### Database Issues
```bash
# Restart database
docker-compose restart postgres

# Check database logs
docker-compose logs postgres

# Access database directly
docker exec -it aspri-postgres psql -U postgres -d aspri
```

### Out of Disk Space
```bash
# Clean up
docker system prune -a
docker volume prune

# Check usage
docker system df
```

### Container Crashes Immediately
```bash
# Check logs
docker logs aspri-backend

# Check with restart policy
docker inspect aspri-backend | grep -A 5 RestartPolicy

# Run without restart to see error
docker-compose up backend
```

## Tips

1. **Always check logs first**: `docker-compose logs -f`
2. **Use -d for background**: `docker-compose up -d`
3. **Rebuild after changes**: `docker-compose up -d --build`
4. **Clean up regularly**: `docker system prune`
5. **Backup before prune**: Backup volumes jika ada data penting
6. **Use .env file**: Jangan hardcode credentials
7. **Monitor resources**: `docker stats` untuk check memory/CPU

## References

- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Docker CLI Reference](https://docs.docker.com/engine/reference/commandline/cli/)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)
