# Fairu (WIP)

> This is the official fairu addon for statamic. Fairu is powerful image and file proxy with the goal in mind to deliver 
your files in an optimized way.

## Features

This addon provides:

- Import functions to bring all your assets into fairu.app
- Tags and field to easily embed fairu hosted files into your next project.

## How to Install

You can install this addon via Composer:

``` bash
composer require sushidev-team/fairu-statamic
```

## How to Use

Follow the instructions at https://docs.fairu.app/docs/statamic/get-started to get started.

### Import

```
php please fairu:setup
```

### Antlers

Using fairu in your application is pretty simple.

```antlers
{{fairu id="ID"}}
    <img src="{{url}}" alt="{{alt}}" />
{{/fairu}}

```

### Detailed

Follow the instructions at https://docs.fairu.app/docs/statamic/get-started to find out what you can do 
