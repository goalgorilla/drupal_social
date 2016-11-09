#!/usr/bin/env bash

# This script is used in Travis in order to trigger an automated build in the Travis of the open_social_testing repo.
# Key is secure so it can not be used in forks. Currently we only trigger on the 8.x-1.x branch.
if [[ $TRAVIS_BRANCH == "8.x-1.x" ]] && [[ $TRAVIS_PULL_REQUEST == "false" ]]
then

    ACCESS_TOKEN=$TRAVIS_ACCESS_TOKEN
    REPO='open_social_testing';

    body='{
    "request": {
      "branch":"master"
    }}'

    curl -s -X POST \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -H "Travis-API-Version: 3" \
      -H "Authorization: token $ACCESS_TOKEN" \
      -d "$body" \
      https://api.travis-ci.org/repo/goalgorilla%2F$REPO/requests

fi
