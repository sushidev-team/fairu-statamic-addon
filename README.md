**This is the official [Fairu](https://fairu.app) addon for [Statamic](https://statamic.com).**

Fairu is your new powerful image and file proxy with the goal in mind to deliver your files in an optimized way.

# Features

This addon provides:

- Import all your assets into [fairu.app](https://fairu.app) using our commands
- Antlers tags making image handling smooth sailing.
- Fieldset to easily embed Fairu hosted files into your new or existing project

# How to use

You can install this addon via Composer:

```shell
composer require sushidev/fairu-statamic
```

## Add env variables

Find your tenant ID in the [tenant settings](https://fairu.app/teams/settings) and [create an API key](https://fairu.app/api) for your application.

```bash
FAIRU_TENANT=[YOUR_TENANT_ID]
FAIRU_TENANT_SECRET=[YOUR_API_KEY_SECRET]
```

## Import

On an existing project, you can run the following command which will automatically import all the connected assets into your fairu account. Depending on on the amount of files it might take some time.

```
php please fairu:setup
```

After the initial import, file paths will be transformed into the new Fairu-ID format dynamically.

# Antlers tags

There are several tags available to generate different code.

## Metadata

> [!NOTE]
> The tags generally don't fetch metadata from the Fairu server and build the file path locally. Use the `fetchMeta` parameter to fetch the asset information from Fairu.

You can fetch metadata by passing `fetchMeta="true"` to the tags, which makes the metadata accessible.

### Lean vs. full fetch

The addon can fetch metadata in two modes:

| Mode | Parameter | Endpoint | Returns |
| --- | --- | --- | --- |
| **Lean** *(default)* | `fetchMeta="true"` | `POST /api/files/meta` | `id, name, width, height, focal_point, alt, caption, is_image, is_video, mime, active` — minimal, no licenses/copyrights/blocks, no N+1 scans. |
| **Full** | `fetchMeta="full"` | `POST /api/files/list` | Full `File` resource including `licenses`, `copyrights`, `block` status, `hasValidLicense`, `amountInvalidLicenses`, etc. Use only when your template logic depends on that data. |

The lean endpoint is **~8× smaller** and issues **3–10× fewer SQL queries** on the Fairu backend. Unless you need license/block data at render time, stick with the default.

Fields accessible via `fetchMeta="true"`:

- name
- alt
- caption
- focal_point (`x-y-zoom` e.g. `40-30-1`)
- focus_css (e.g. `40% 30%`)
- width | height
- is_image | is_video | mime
- active

Use `fetchMeta="full"` when you additionally need `description`, `copyrights`, `licenses`, `block`, `size`, `fingerprint`, `extension`, or any other non-rendering metadata.

## Automatic meta coalescing

When a page uses many `{{ fairu:image }}` / `{{ fairu:url ... fetchMeta="true" }}` tags — especially when they come from nested components, bards, or loops that you don't control up front — the addon automatically collapses every meta fetch on the page into a **single** batched API call.

### How it works

1. Each tag emits an opaque placeholder token instead of immediately fetching meta.
2. A response middleware (`CoalesceFairuMeta`) queues all ids while the view renders.
3. After Antlers finishes, it fires **one** `POST /api/files/meta` call for every unique id across the whole response.
4. The placeholders are replaced in-place with the final `<img>` / URL output.

Result: a page with 15, 30, or 300 images issues exactly one meta round-trip per request, independent of template nesting.

### Enabling / disabling

Enabled by default. Toggle via environment variable:

```bash
FAIRU_COALESCE_META=false
```

…or in `config/statamic/fairu.php`:

```php
'coalesce_meta' => env('FAIRU_COALESCE_META', true),
```

### Caveats

- **Do not wrap fairu tags in `{{ cache }}` blocks.** The placeholder would get cached without the corresponding queue entry, so subsequent cache-hit renders can't resolve it. Use Statamic's response-level static caching instead — the middleware runs *before* static caching stores, so the cached HTML contains the final output.
- **String operations on tag output** (e.g. `{{ fairu:url ... | upper }}`) will operate on the placeholder, not the URL. Rare, but worth noting.
- Only `text/html` responses are rewritten. JSON, streamed, and binary responses pass through untouched.
- Tags without `fetchMeta` or with an explicit `name` parameter render immediately and don't go through the coalescer — no change in behaviour.

### Requirements

The `POST /api/files/meta` endpoint is required on your Fairu backend for the default (lean) mode and for meta coalescing. It ships with `fairu-app` alongside this addon version. If you run an older Fairu deployment that doesn't expose the endpoint yet, set `FAIRU_COALESCE_META=false` and use `fetchMeta="full"` until the backend is updated.

## Available parameters

| Field            | Description                        | fairu | :url  | :image | :images |
| ---------------- | ---------------------------------- | ----- | ----- | ------ | ------- |
| **⁠id**          | The file ID                        | **✓** | **✓** | **✓**  | **✓**   |
| **⁠name**        | Custom filename                    | **✓** | **✓** | **✓**  | **✓**   |
| **alt**          | Custom alt                         | **✓** |       | **✓**  | **✓**   |
| **⁠width**       | Resize image width                 | **✓** |       | **✓**  | **✓**   |
| **⁠height**      | Resize image height                | **✓** |       | **✓**  | **✓**   |
| **⁠quality**     | Image quality (default: 90)        | **✓** |       | **✓**  | **✓**   |
| **⁠sources**     | Semicolon separated srcset entries | **✓** |       | **✓**  | **✓**   |
| **ratio**        | Aspect ratio for sources           | **✓** |       | **✓**  | **✓**   |
| **⁠format**      | Convert image format               | **✓** |       | **✓**  | **✓**   |
| **fit**          | cover / contain the image          | **✓** |       | **✓**  | **✓**   |
| **⁠focal_point** | Focal point for cropping           | **✓** |       | **✓**  | **✓**   |
| **timestamp**   | Video thumbnail timestamp (HH:MM:SS.mmm) | **✓** | **✓** | **✓**  | **✓**   |
| **fetchMeta**   | `"true"` for lean meta (default), `"full"` for full `File` resource, `"false"` to skip | **✓** | **✓** | **✓**  | **✓**   |

## {{ fairu }}

Get the file and get access to image properties.

```antlers
{{ fairu id="ID" alt="Alt text" }}
    <img
        src="{{ url }}"
        alt="{{ alt }}" />
{{ /fairu }}
```

## {{ fairu:url }}

Get the URL of a file.

```html
{{ fairu:url id="ID" name="filename.webp" }}

<!-- Outputs -->
https://fairu.app/files/[UUID]/filename.webp
```

If you don't know the filename (e.g. for video files where the extension matters for browser playback), pass `fetchMeta="true"` to resolve the real filename from Fairu:

```html
{{ fairu:url id="ID" fetchMeta="true" }}

<!-- Outputs (extension resolved from meta) -->
https://fairu.app/files/[UUID]/hero.mp4
```

## {{ fairu:image }}

Generate a complete HTML image tag.

```html
{{ fairu:image id="ID" width="800" height="600" class="my-image" alt="Image description" }}

<!-- Outputs -->

<img
    src="https://fairu.app/files/[UUID]/filename.webp?width=800&height=600"
    alt="Image description"
    class="my-image" />
```

## {{ fairu:images }}

Generate multiple HTML image tags (see [[#{{ fairu image }}]])

```html
{{ fairu:images ids="[IDS]" width="800" height="600" class="my-image" alt="Image description" }}

<!-- Outputs -->

<img
    src="https://fairu.app/files/[UUID1]/filename1.webp?width=800&height=600"
    alt="Image description" />
<img
    src="https://fairu.app/files/[UUID2]/filename2.webp?width=800&height=600"
    alt="Image description" />
...
```

## Responsive Images

With most of our tags you have easy access to implementing responsive images using the native `srcset` and `sizes` properties to define when which source (version of the image) should be used. See [the mdn documentation](https://developer.mozilla.org/en-US/docs/Web/HTML/Guides/Responsive_images) for a great introduction into implementing responsive images.

### Implementation

The `sources` attribute provides a way to easily pass an array of widths, breakpoints and optionally heights to generate a `srcset` property that includes all listed sources.

This allows you to pass the sources property and use the calculated `srcset` property along with a fitting `sizes` to define which sources should be used when:

```html
{{ fairu id="ID" sources="100,100w;512,512w" }}
<img
    src="{{ url }}"
    srcset="{{srcset}}"
    sizes="(min-width: 800px) 100vw; 800px" />
{{ /fairu }}

<!-- Outputs -->
<img
    src="https://fairu.app/files/[UUID1]/filename1.webp"
    srcset="
        https://fairu.app/files/[UUID1]/filename1.webp?width=100 100w,
        https://fairu.app/files/[UUID1]/filename1.webp?width=512 512w
    "
    sizes="(min-width: 800px) 100vw; 800px" />
```

### Formats

Each pair consists of:

- A width value (in pixels) that defines the image width at that breakpoint
- A height value (in pixels) that defines the image height at that breakpoint **(optional)**
- A breakpoint value (with 'w' suffix) that corresponds to the viewport width

```html
sources="[width1],[breakpoint1]w;[width2],[breakpoint2]w"

<!--
Example:
"320,320w;480,800w;768,1200w;1200,1600w;1920,2400w" 
-->

sources="[width1],[height1],[breakpoint1]w;[width2],[height2],[breakpoint2]w"

<!--
Example:
"320,150,320w;480,280,800w,768,1200w;1200,1600w,1920:2400w"
-->
```

#### Ratio

If all sources should have the same aspect ratio, you can use the `ratio` attribute as a shortcut without height:

```antlers
{{ fairu id="ID" sources="320,320w;480,800w;768,1200w;1200,1600w;1920,2400w" ratio="16/9" }}
```

This calculates all heights accordingly.

### The sizes attribute

The ⁠sizes attribute tells the browser how large the image will be displayed at different viewport widths. If not provided, it will be auto-generated based on the breakpoints in the ⁠sources parameter.

For a full-width responsive image, you can leave the sizes property empty or you can use:

```
sizes="100vw"
```

For more complex layouts, add media queries and a default or fallback value as last value:

```
sizes="(min-width: 1200px) 1200px, (min-width: 768px) 800px, 100vw"
```

## Details

For more information, visit the documentation at https://docs.fairu.app/docs/addons/00-statamic to find out what else you can do with Fairu.
