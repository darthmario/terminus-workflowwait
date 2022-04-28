# Terminus Workflow Wait Command

A simple plugin for Terminus-CLI to track most recent workflow status.

Adds commands 'workflowwait' to Terminus. 

## Configuration

This commands require no configuration

## Usage
* `terminus workflowwait site.env [max wait time]`

## Installation

To install this plugin using Terminus 3:
```
terminus self:plugin:install path-to/terminus-workflow-wait
```

On older versions of Terminus:
```
mkdir -p ~/.terminus/plugins
curl https://github.com/pantheon-systems/terminus-plugin-example/archive/2.x.tar.gz -L | tar -C ~/.terminus/plugins -xvz
```
