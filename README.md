**This is the official [Fairu](https://fairu.app) addon for [Statamic](https://statamic.com).**

Fairu is your new powerful image and file proxy with the goal in mind to deliver your files in an optimized way.

> [!WARNING]
> **Upgrading from v2 to v3:** Fairu is now gated behind dedicated permissions. After upgrading, grant the new **Fairu Assets** permissions in each role under **Users → Roles** — otherwise non-super users lose Fairu access (nav hidden, action endpoints return 403). Already-attached assets in fields still render for everyone; only interactive actions are gated.
>
> Permissions (all under the **Fairu Assets** group): `view`, `upload`, `edit`, `rename`, `move`, `delete` fairu assets. Independent — `view` is required for anything that needs the folder picker (move, browsing in fields), so grant it alongside any other action.

# Features

This addon provides:

- Import all your assets into [fairu.app](https://fairu.app) using our commands
- Antlers tags making image handling smooth sailing.
- Fieldset to easily embed Fairu hosted files into your new or existing project
- Galleries and channels (video shows and podcasts) rendered straight from Fairu
- A control panel utility for clearing the caches and checking the connection

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
| **raw**          | `"true"` returns the untouched original (no `?quality`/`focal`/transform query). Use for PDFs and other files that must not be routed through the image proxy | **✓** | **✓** | **✓**  | **✓**   |
| **download**     | `"true"` emits a same-origin URL that forces a browser download (`Content-Disposition: attachment`) instead of opening inline. **`{{ fairu:url }}` only** — ignored on all other tags | | **✓** | | |

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

### Raw originals (PDFs etc.)

By default the URL carries a transform query (`?quality=90&focal=…`) so images can be
processed by the proxy. For files that must not be transformed — PDFs, ZIPs, original
documents — pass `raw="true"` to get the untouched original:

```html
{{ fairu:url id="ID" name="plan.pdf" raw="true" }}

<!-- Outputs (no query) -->
https://files.fairu.app/[UUID]/plan.pdf
```

`raw` works on every tag (`{{ fairu }}`, `{{ fairu:image }}`, `{{ fairu:images }}` too).

### Forcing a download

Files are served inline by the CDN, so linking to them opens the file in a new tab.
The HTML `download` attribute is ignored because the file lives on another origin.
Pass `download="true"` to route the URL through a same-origin endpoint that streams the
file with a `Content-Disposition: attachment` header, so the browser saves it:

```html
<a href="{{ fairu:url id="ID" name="plan.pdf" download="true" }}">Download plan</a>

<!-- href resolves to a same-origin route that forces the download -->
/fairu/download/[UUID]/plan.pdf
```

Combine with `fetchMeta="true"` when you don't know the filename — the resolved name
becomes the downloaded file's name.

> **`download` only works on `{{ fairu:url }}`.** Unlike `raw`, it is ignored on
> `{{ fairu }}`, `{{ fairu:image }}`, and `{{ fairu:images }}` — those tags emit
> the CDN URL directly. To force a download, wrap a `{{ fairu:url ... download="true" }}`
> in your own `<a>` tag.

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

# Galleries

A gallery in Fairu is a folder somebody curated: sorted, given a cover, a date, a
place, and with the copyrights already attached. Point a page at one instead of
rebuilding it as a `fairu` field holding two hundred ids kept in order by hand.

## {{ fairu:gallery }}

Renders its body once with the gallery in scope. Every transform parameter of
`{{ fairu:image }}` works here and applies to every item.

```antlers
{{ fairu:gallery id="GALLERY_ID" width="1200" sources="320,320w;800,1200w" }}
    <h2>{{ name }}</h2>
    <p>{{ date }} · {{ location }}</p>

    {{ items }}
        <img src="{{ url }}" srcset="{{ srcset }}" alt="{{ alt }}" style="object-position: {{ focus_css }}">
    {{ /items }}

    <small>{{ copyright_text }}</small>
{{ /fairu:gallery }}
```

Available in scope: `id`, `name`, `description`, `date`, `location`,
`copyright_text`, `copyrights`, `cover_image`, `items`, `total_items`, `paginate`.
Each item carries `id`, `name`, `alt`, `caption`, `mime`, `width`, `height`,
`focal_point`, `focus_css`, `blurhash`, `duration`, `url`, `srcset`, `is_image`,
`is_video`, `is_audio`.

| Parameter | Description |
| --- | --- |
| `id` | The gallery ID (required) |
| `limit` / `perPage` | How many items to load (default 50) |
| `page` | Switches to the paginated list; `paginate` then holds `total`, `currentPage`, `lastPage`, `hasMorePages` |
| `orderBy` / `orderDirection` | Overrides the sorting configured on the gallery |
| *image parameters* | `width`, `height`, `quality`, `format`, `fit`, `focal_point`, `sources`, `ratio`, `raw` — applied to every item |

## {{ fairu:galleries }}

The index page. Lists the galleries of the workspace that are not excluded from
listings.

```antlers
{{ fairu:galleries perPage="12" from="2026-01-01" }}
    {{ galleries }}
        <a href="/galerien/{{ id }}">
            <img src="{{ cover_image:url }}" alt="{{ cover_image:alt }}">
            {{ name }}
        </a>
    {{ /galleries }}
{{ /fairu:galleries }}
```

Parameters: `page`, `perPage`, `search`, `from`, `until`, `orderBy`,
`orderDirection`, plus the image parameters for the covers.

## The `fairu_gallery` fieldtype

A picker that stores the gallery id, so an editor chooses the gallery and the
template stays fixed:

```antlers
{{ fairu:gallery :id="my_gallery_field" width="1200" }} … {{ /fairu:gallery }}
```

# Channels and podcasts

A channel is a show — video or audio. Fairu holds its seasons, episodes, show
notes, release windows, player settings and chapters, and publishes a podcast
feed for it.

## {{ fairu:channel }}

Addressed by `id` or by `slug`, which is unique inside the workspace and reads
better in a route.

```antlers
{{ fairu:channel slug="die-werkstatt" }}
    <h1>{{ name }}</h1>
    <img src="{{ cover_image:url }}" alt="{{ cover_image:alt }}">
    <link rel="alternate" type="application/rss+xml" href="{{ feed_url }}">

    {{ episodes }}
        <article>
            <h2>{{ number }} · {{ title }}</h2>
            <p>{{ description }}</p>
            <span>{{ duration_for_humans }}</span>
            {{ embed_html }}
        </article>
    {{ /episodes }}
{{ /fairu:channel }}
```

> [!IMPORTANT]
> **Only what a visitor may see.** The addon deliberately reads the *public*
> channel queries. It holds a workspace API key, so the authenticated query would
> hand a template the drafts and the episodes whose release window has not opened
> — and a template looping over `episodes` has no way to tell. Pass
> `preview="true"` for the workspace's own view; use it in a live preview, not in
> a public template.

In scope: `id`, `name`, `slug`, `kind`, `is_audio`, `is_video`, `description`,
`author`, `copyright`, `itunes_type`, `itunes_category`, `itunes_subcategory`,
`explicit`, `cover_image`, `player_settings`, `feed_url`, `embed`, `episodes`,
and `seasons` when asked for.

Each episode carries `id`, `number`, `title`, `description`, `show_notes`,
`published_at`, `episode_type`, `explicit`, `orientation`, `aspect_ratio`,
`duration`, `duration_for_humans`, `url` (the media file), `asset`, `embed_url`,
`embed_html`, `embed_iframe`.

| Parameter | Description |
| --- | --- |
| `id` / `slug` | Which channel. One of them is required |
| `episode` | An episode ID; that episode is additionally exposed as `episode` for an episode page |
| `seasons` | `"true"` also nests the episodes under their seasons |
| `episodes` | `"false"` skips the flat episode list |
| `embed_width` | Width written into the embed snippets |
| `preview` | `"true"` reads the workspace's own view instead of the visitor's |

`embed_html` is the snippet with Fairu's loader script, which keeps the frame at
the height the player reports. `embed_iframe` is the same player as a plain
iframe for hosts where a script cannot run — a newsletter, an editor that strips
`<script>`.

## {{ fairu:channels }}

```antlers
{{ fairu:channels }}
    {{ channels }}
        <a href="/podcasts/{{ slug }}">{{ name }} ({{ kind }})</a>
    {{ /channels }}
{{ /fairu:channels }}
```

Parameters: `page`, `perPage`, `search`, `preview`.

## The `fairu_channel` fieldtype

Stores the channel id. The picker also offers unpublished shows — the page that
publishes one has to be built before it goes live.

## Caching

Galleries follow `caching_meta`. Channels get their own, shorter pair, because an
episode goes live at a moment somebody chose:

```php
'caching_channels' => [5, 15], // fresh for 5 minutes, stale-while-revalidate until 15
```

# Utilities → Fairu

The addon registers a **Fairu** utility in the control panel (**Utilities → Fairu**),
gated by the `access fairu utility` permission that Statamic registers for it.

## Metadata cache

Everything the addon reads from Fairu — filenames, alt texts, captions, dimensions,
focal points — is cached for `caching_meta` (60 minutes fresh, 120 stale by default).
So a caption fixed in Fairu keeps rendering stale on the site for up to two hours,
and until now the only way out was `php artisan cache:clear`, which needs shell
access and takes the rest of the application cache with it.

The utility clears the addon's cache and nothing else. Cache **tags** would be the
obvious tool, but they only exist on redis and memcached, so the version rides in
the key instead: every key the addon writes is prefixed `fairu.v{n}.`, and clearing
bumps `n`. That orphans every entry in a single write on any driver, including the
file driver, and the orphans expire on their own schedule.

Tick **Also flush the static page cache** when static caching is on — a statically
cached page already has the old metadata baked into its HTML, so clearing the
metadata behind it changes nothing a visitor sees.

## Delivery cache

Clears what Fairu, its proxy and every CDN in front of it are holding for a file
— the other half of the story, and the one the addon cannot do on its own. Paste
up to 50 file IDs after replacing a file's content in Fairu when the old version
is still being served.

Requires an API key carrying the `cache::purge` permission and a Fairu backend
that exposes the `purgeFairuCache` mutation. Purging also rotates each file's
ETag, so a CDN that kept the response revalidates into a miss rather than being
told its copy is still good.

## Connection

Shows which workspace the site talks to (tenant, API and proxy URL) and tests the
credentials against `GET /api/users/scope` on demand — the first thing to check
when images render as broken links.

## Details

For more information, visit the documentation at https://docs.fairu.app/docs/addons/00-statamic to find out what else you can do with Fairu.
