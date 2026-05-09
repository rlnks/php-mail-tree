# rlnks/php-mail-tree

A PHP library for building HTML emails using an intuitive **object-tree nesting** approach. Structure your email exactly the way you think it — each node is a named property on its parent, and the hierarchy of your PHP code mirrors the hierarchy of the rendered HTML.

## Installation

```bash
composer require rlnks/php-mail-tree
```

Requires PHP 8.2+.

## The concept

Most email builders make you concatenate strings or call sequential methods. This library lets you build an **object tree** instead. Assign child nodes as named properties — the name you choose becomes documentation.

```php
$email->body = new Body();
$email->body->header = new Container();
$email->body->header->logo_col = new Column();
$email->body->header->logo_col->logo = new Image($src, $alt);
```

Each node knows its place in the tree. Call `$email->build()` once and the entire tree renders itself recursively, cascading styles top-down.

## Quick start

```php
use Rlnks\MailTree\Anchor;
use Rlnks\MailTree\Body;
use Rlnks\MailTree\Column;
use Rlnks\MailTree\Container;
use Rlnks\MailTree\EmailDocument;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\Text;

$email = new EmailDocument([
    'body' => ['background-color' => '#eeeeee'],
    'text' => ['font-family' => 'Arial, sans-serif', 'color' => '#333333'],
]);
$email->setSubject('Hello world');

// Build the tree
$email->body = new Body();

$email->body->wrapper = new Container(['container' => ['width' => '600px']], mso: true);
$email->body->wrapper->col = new Column(['column' => ['width' => '560px']]);
$email->body->wrapper->col->title = new Text('Welcome!', 'h1');
$email->body->wrapper->col->intro = new Text('Thanks for signing up.', 'div');
$email->body->wrapper->col->cta   = new Anchor('https://example.com', ['a' => ['color' => '#fff']]);
$email->body->wrapper->col->cta->label = new Text('Get started', 'span');

echo $email->build();
```

## Style cascade

Styles flow **top-down** through the tree. Each node receives its parent's full style array and merges its own overrides on top. This means you set global defaults once on `EmailDocument` and override only what you need closer to the leaf.

```php
// Global defaults (set once)
$email = new EmailDocument([
    'text' => ['font-size' => '14px', 'color' => '#333'],
    'h1'   => ['font-size' => '28px', 'color' => '#c00'],
]);

// Local override (only affects this node and its children)
$section->body->title = new Text('Hello', 'h1', ['h1' => ['color' => '#00c']]);
```

Style arrays are always **keyed by element type** to avoid ambiguity:

```php
// Correct
new Container(['container' => ['background-color' => '#fff', 'width' => '600px']]);
new Column(['column'    => ['width' => '540px', 'padding' => '0 10px']]);
new Text('…', 'div',  ['div'       => ['font-size' => '16px']]);
new Image($src, $alt,  ['img'       => ['max-width' => '200px']]);
new Anchor($href,       ['a'         => ['color' => '#c00']]);
```

## deepclone

The `deepclone()` helper (auto-loaded) deep-copies any node tree. Use it to duplicate pre-built reusable blocks before customizing them:

```php
$spacer = new Container(['container' => ['height' => '20px']]);
$spacer->col = new Column();
$spacer->col->space = new Text('&nbsp;', 'div');

// Reuse the same spacer structure everywhere
$section->before = deepclone($spacer);
$section->after  = deepclone($spacer);
```

## API reference

### `EmailDocument`

| Method | Description |
|---|---|
| `__construct(array $style = [])` | Initialize with style overrides merged into defaults |
| `setSubject(string $title)` | Set the `<title>` and email subject |
| `addLink(string $href, string $rel)` | Add a `<link>` tag to `<head>` (e.g., Google Fonts) |
| `setStyle(array $style)` | Merge additional styles after construction |
| `getStyle(): array` | Get the full resolved style array |
| `build(): string` | Render the full HTML document |

### `Body`

| Method | Description |
|---|---|
| `__construct(array $style = [])` | Style overrides for the `<body>` element |
| `setCSS(string $css)` | Inject a `<style>` block (for responsive CSS) |

### `Container`

Renders as `<table><tbody><tr>…</tr></tbody></table>`. Each direct child should be a `Column`.

| Method | Description |
|---|---|
| `__construct(array $style = [], bool $mso = false)` | `mso: true` wraps with Outlook conditional comments |
| `setClass(string $class)` | Add a CSS class to the `<table>` |
| `setID(string $id)` | Add an id to the `<table>` |

### `Column`

Renders as `<td>`. Direct child of `Container`.

| Method | Description |
|---|---|
| `__construct(array $style = [])` | `['column' => ['width' => '…']]` |
| `setClass(string $class)` | Add a CSS class to the `<td>` |

### `Text`

Renders any inline or block text element. With `$tag = null` it acts as a transparent wrapper — useful for grouping child nodes without adding markup.

| Constructor | Description |
|---|---|
| `new Text(string $text, ?string $tag = null, array $style = [])` | `$tag` can be `h1`, `h2`, `div`, `p`, `span`, etc. |

### `Image`

Renders a self-closing `<img>`. Emits `width`/`height` HTML attributes only when the CSS value is a plain pixel number (not `%`, `auto`, etc.).

| Method | Description |
|---|---|
| `__construct(string $src, string $alt, array $style = [])` | |
| `setSrc(string $src, ?string $alt = null)` | Update src (and optionally alt) after construction |

### `Anchor`

Renders `<a href="…">…</a>`. Children render inside the tag.

| Method | Description |
|---|---|
| `__construct(string $href = '#', array $style = [])` | |
| `setLink(string $href)` | Update the href after construction |

## Using as a back-end for a visual builder

Because the tree is just PHP objects, it maps naturally to a JSON payload from a front-end drag-and-drop builder:

```php
function buildFromJson(array $node): Renderable {
    return match($node['type']) {
        'container' => tap(new Container($node['style'] ?? []), fn($c) =>
            array_walk($node['children'] ?? [], fn($child, $key) => $c->$key = buildFromJson($child))
        ),
        'column' => tap(new Column($node['style'] ?? []), fn($col) =>
            array_walk($node['children'] ?? [], fn($child, $key) => $col->$key = buildFromJson($child))
        ),
        'text'   => new Text($node['content'], $node['tag'] ?? null, $node['style'] ?? []),
        'image'  => new Image($node['src'], $node['alt'] ?? '', $node['style'] ?? []),
        'anchor' => tap(new Anchor($node['href'], $node['style'] ?? []), fn($a) =>
            array_walk($node['children'] ?? [], fn($child, $key) => $a->$key = buildFromJson($child))
        ),
        default  => throw new \InvalidArgumentException("Unknown node type: {$node['type']}"),
    };
}
```

## License

MIT
