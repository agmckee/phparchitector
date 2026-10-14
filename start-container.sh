#!/bin/bash

# Start or create the Podman container for development

CONTAINER_NAME="php-analysis-dev"
IMAGE="php:8.4-cli"
PROJECT_DIR="$(pwd)"

echo "Setting up Podman container for PHP Architecture Analysis Tool..."
echo "================================================================"
echo ""

# Check if container exists
if podman ps -a --filter "name=$CONTAINER_NAME" --format "{{.Names}}" | grep -q "$CONTAINER_NAME"; then
    echo "Container '$CONTAINER_NAME' exists."
    
    # Check if it's running
    if podman ps --filter "name=$CONTAINER_NAME" --format "{{.Status}}" | grep -q "Up"; then
        echo "Container is already running."
        echo ""
        echo "Container info:"
        podman ps --filter "name=$CONTAINER_NAME" --format "table {{.ID}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}"
        exit 0
    else
        echo "Container exists but is stopped. Starting it..."
        podman start $CONTAINER_NAME
        exit 0
    fi
else
    echo "Creating new container '$CONTAINER_NAME' from $IMAGE..."
    podman run -d \
        --name $CONTAINER_NAME \
        -v $PROJECT_DIR:/app \
        -w /app \
        $IMAGE \
        sleep infinity
    
    echo "Container created and started."
fi

echo ""
echo "Container info:"
podman ps --filter "name=$CONTAINER_NAME" --format "table {{.ID}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}"

echo ""
echo "To run tests: ./run-tests.sh"
echo "To enter container: podman exec -it $CONTAINER_NAME bash"
