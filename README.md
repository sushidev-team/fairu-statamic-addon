# Fairu (WIP)

> This is the official fairu addon for statamic. Fairu is powerful image and file proxy with the goal in mind to deliver
> your files in an optimized way.

## Features

This addon provides:

- Import functions to bring all your assets into fairu.app
- Tags and field to easily embed fairu hosted files into your next project.

## How to Install

You can install this addon via Composer:

```bash
composer require sushidev/fairu-statamic
```

## How to Use

Follow the instructions at https://docs.fairu.app/docs/addons/statamic to get started.

### Add env variables

```bash
FAIRU_TENANT=[YOUR_TENANT_ID]
FAIRU_TENANT_SECRET=[YOUR_API_KEY_SECRET]
```

### Import

If you already have some assets you can run the following command. It will automatically import all the connected assets into your fairu account. Depending on on the amount of files it might take some time.

```
php please fairu:setup
```

## Antler tags

There are several tags available to generate different code:

### General file

Get the file and get access to image properties.

```antlers
{{ fairu id="ID" }}
    <img
        src="{{ url }}"
        alt="{{ alt }}" />
{{ /fairu }}
```

### Direct URL

Get just the URL of a file.

```antlers
{{ fairu:url id="ID" name="filename" }}
```

**Parameters**

- `id` - The file ID (required) 
- `⁠name` - Custom filename (optional)
- `⁠width` - Resize image width (optional)
- `⁠height` - Resize image height (optional)
- `⁠quality` - Image quality (default: 90)
- `⁠format` - Convert image format (optional)
- `⁠focal_point` - Focal point for cropping (optional)

Note that we accept for ID a string or array string.
Preferable it should be the id of the image. But if you import your files we also accept the previous path which will be transformed into the id.

### Image Tag

Generate a complete HTML image tag.

```antlers
{{ fairu:image id="ID" width="800" height="600" class="my-image" alt="Image description" }}
```

**Parameters**

- `⁠id` - The file ID (required)
- `⁠name` - Custom filename (optional)
- `⁠width` - Image width attribute (optional)
- `⁠height` - Image height attribute (optional)
- `⁠class` - CSS class(es) (optional)
- `⁠alt` - Alt text (optional, falls back to file description)

Note that we accept for ID a string or array string.
Preferable it should be the id of the image. But if you import your files we also accept the previous path which will be transformed into the id.

### Responsive Images

Generate a responsive image with srcset and sizes attributes.

```antlers
{{ fairu:sources id="ID" sources="320:320w,480:800w,768:1200w,1200:1600w,1920:2400w" sizes="100vw" class="responsive-image" alt="Responsive image" }}
```

Note that we accept for ID a string or array string.
Preferable it should be the id of the image. But if you import your files we also accept the previous path which will be transformed into the id.

**Parameters**

- `⁠id` - The file ID (required)
- `⁠name` - Custom filename (optional)
- `⁠sources` - Breakpoint and width pairs (format: "breakpoint:width,breakpoint:width")
- `⁠sizes` - The sizes attribute (optional, auto-generated if not provided)
- `⁠width` - Image width attribute (optional)
- `⁠height` - Image height attribute (optional)
- `⁠class` - CSS class(es) (optional)
- ⁠`alt` - Alt text (optional, falls back to file description)

Note that we accept for ID a string or array string.
Preferable it should be the id of the image. But if you import your files we also accept the previous path which will be transformed into the id.

### How the sources parameter works

The ⁠sources parameter defines the breakpoints and image widths in the format:

```
"breakpoint1:width1,breakpoint2:width2,breakpoint3:width3"
```

For example:

```
"320:320w,480:800w,768:1200w,1200:1600w,1920:2400w"
```

Each pair consists of:

- A breakpoint value (in pixels) that corresponds to the viewport width
- A width value (with 'w' suffix) that defines the image width at that breakpoint

The tag generates an appropriate ⁠srcset attribute that allows browsers to select the most suitable image based on viewport size and device pixel ratio.

### The sizes attribute

The ⁠sizes attribute tells the browser how large the image will be displayed at different viewport widths. If not provided, it will be auto-generated based on the breakpoints in the ⁠sources parameter.

For a full-width responsive image, you can leave the sizes property empty or you can use:

```
sizes="100vw"
```

For more complex layouts:

```
sizes="(min-width: 1200px) 1200px, (min-width: 768px) 800px, 100vw"
```

This powerful combination of ⁠srcset and ⁠sizes ensures optimal image loading across all devices and screen sizes.

### Detailed

Follow the instructions at https://docs.fairu.app/docs/addons/statamic to find out what you can do.
