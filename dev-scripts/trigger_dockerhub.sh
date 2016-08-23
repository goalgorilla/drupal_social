#!/usr/bin/env bash

# This script is used in Travis in order to trigger an automated build in the DockerHub.
# Key is secure so it can not be used in forks. Currently we only trigger on the 8.x-1.x branch.

echo $TRAVIS_BRANCH;
curl -H "Content-Type: application/json" --data '{"build": true}' -X POST https://registry.hub.docker.com/u/goalgorilla/drupal_social/trigger/$DOCKERHUB_TOKEN/
