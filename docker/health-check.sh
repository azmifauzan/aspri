#!/bin/bash

# Health check script for Docker container
set -e

# Check if Apache is responding
if curl -sf http://localhost/ > /dev/null 2>&1; then
    exit 0
fi

exit 1
