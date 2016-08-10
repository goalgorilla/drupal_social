#!/usr/bin/env bash

# Lets install Docker first.
sudo apt-get clean
sudo apt-get update
sudo apt-get -y -o Dpkg::Options::="--force-confnew" install docker-engine
docker --version

# Lets install via docker-compose.
sudo rm /usr/local/bin/docker-compose || true
curl -L https://github.com/docker/compose/releases/download/${DOCKER_COMPOSE_VERSION}/docker-compose-`uname -s`-`uname -m` > docker-compose
chmod +x docker-compose
sudo mv docker-compose /usr/local/bin
docker-compose --version
docker-compose -f docker-compose-travis.yml up -d
docker ps -a
