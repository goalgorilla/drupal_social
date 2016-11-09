#!/usr/bin/env bash

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
