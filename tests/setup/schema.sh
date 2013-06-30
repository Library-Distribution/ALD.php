#! /usr/bin/env bash

# get the current branch
if [ ! -n "$TRAVIS_BRANCH" ]
then
  if [ ! -d ".git" ]
  then
    CURRENT_BRANCH="master"
  else
    CURRENT_BRANCH=`git rev-parse --abbrev-ref HEAD`
  fi
else
  CURRENT_BRANCH="$TRAVIS_BRANCH"
fi

# check if the current branch also exists on ALD-API
curl -s -f "https://api.github.com/repos/Library-Distribution/ALD-API/branches/$CURRENT_BRANCH" &> /dev/null
if [ $? == 22 ]
then
  SCHEMA_BRANCH="master"
else
  SCHEMA_BRANCH="$CURRENT_BRANCH"
fi

# fetch the schema from github
curl -s -f -H "Accept: application/vnd.github.3.raw" -o "package.xsd" "https://api.github.com/repos/Library-Distribution/ALD-API/contents/schema/package.xsd?ref=$SCHEMA_BRANCH"