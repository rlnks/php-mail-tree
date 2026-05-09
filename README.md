# rlnks/php-mail-tree

A PHP library for building HTML emails using an intuitive **object-tree nesting** approach. Structure your email exactly the way you think it — each node is a named property on its parent, and the hierarchy of your PHP code mirrors the hierarchy of the rendered HTML.

## Installation

```bash
composer require rlnks/php-mail-tree
```

Requires PHP 8.2+.

---

## The concept

Most email builders make you concatenate strings or call sequential methods. This library lets you build an **object tree** instead. Assign child nodes as named properties — the name you choose becomes documentation.

```php
$email->body = new Body();
$email->body->header = new Container();
$email->body->header->logo_col = new Column();
$email->body->header->logo_col->logo = new Image($src, $alt);
```

Each node knows its place in the tree. Call `$email->build()` once and the entire tree renders itself recursively, cascading styles top-down.

---

## Quick start with StyleSheet + Presets

```php
use Rlnks\MailTree\Body;
use Rlnks\MailTree\EmailDocument;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;
use Rlnks\MailTree\Preset\Button;
use Rlnks\MailTree\Preset\FullWidthImage;
use Rlnks\MailTree\Preset\Section;
use Rlnks\MailTree\Preset\Spacer;
use Rlnks\MailTree\Preset\TwoColumn;

// 1. Define your theme once
$sheet = new StyleSheet([
    'primaryColor'   => '#e63946',
    'fontFamily'     => "Poppins, Arial, sans-serif",
    'containerWidth' => 600,
    'marginWidth'    => 30,
]);

// 2. Bootstrap the email
$email = new EmailDocument($sheet->emailStyle());
$email->setSubject('Your order is confirmed');
$email->addLink('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700', 'stylesheet');

$email->body = new Body();
$email->body->setCSS($sheet->responsiveCss());

// 3. Build the tree with presets
$email->body->banner    = FullWidthImage::make($heroSrc, 'Hero', href: $heroUrl, sheet: $sheet);
$email->body->gap1      = Spacer::make(sheet: $sheet);

$email->body->intro     = Section::make(sheet: $sheet);
$email->body->intro->body->title = new Text('Order confirmed!', 'h1');
$email->body->intro->body->desc  = new Text('Thanks for your purchase.', 'div');
$email->body->intro->body->cta   = Button::make('View order', $orderUrl, sheet: $sheet);

$email->body->gap2      = Spacer::make('30px');

$email->body->features  = TwoColumn::make(sheet: $sheet);
$email->body->features->left->img   = new Image($img1, 'Feature A');
$email->body->features->right->title = new Text('Feature A', 'h2');

echo $email->build();
```

---

## StyleSheet

The `StyleSheet` does two things:

### 1. Theme → style arrays

Define your brand variables once, then call the generator methods to get properly-nested style arrays for each framework class.

```php
$sheet = new StyleSheet([
    'primaryColor'   => '#c0392b',
    'textColor'      => '#333333',
    'bgColor'        => '#f4f4f4',
    'containerBg'    => '#ffffff',
    'borderColor'    => '#dddddd',
    'fontFamily'     => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
    'baseFontSize'   => '14px',
    'lineHeight'     => '150%',
    'containerWidth' => 600,
    'marginWidth'    => 30,
    'spacerHeight'   => '20px',
    'buttonRadius'   => '4px',
    'buttonHeight'   => 50,
    'buttonWidth'    => 200,
    'buttonFontSize' => '16px',
]);

$email = new EmailDocument($sheet->emailStyle());
$email->body->setCSS($sheet->responsiveCss());
```

| Generator method | Returns style array for |
|---|---|
| `emailStyle()` | `EmailDocument` constructor |
| `containerStyle()` | `Container` constructor |
| `outerContainerStyle()` | Outer MSO-bounding Container |
| `marginColumnStyle()` | Gutter `Column` |
| `bodyColumnStyle()` | Content `Column` (1-col) |
| `twoColStyle()` | Each column in 2-col layout |
| `threeColStyle()` | Each column in 3-col layout |
| `spacerStyle($height)` | Spacer `div` style |
| `buttonContainerStyle()` | Table-based button container |

### 2. Named-style registry

```php
$sheet->define('hero', [
    'container' => ['background-color' => '#003366'],
    'h1'        => ['color' => '#ffffff', 'font-size' => '32px'],
    'div'       => ['color' => '#ccddff'],
]);

$section->setStyle($sheet->get('hero'));

// Extend later
$sheet->extend('hero', ['container' => ['border-bottom' => '3px solid #ff0']]);

// Retrieve with inline overrides
$style = $sheet->get('hero', ['h1' => ['font-size' => '24px']]);
```

### responsiveCss()

Generates the full `<style>` block for `Body::setCSS()`.

Includes:
- Outlook.com / ExternalClass resets
- Global table `border-collapse` and `mso-table-lspace` resets
- iOS `-webkit-text-size-adjust` reset
- Apple Mail auto-detected link styling (`a[x-apple-data-detectors]`)
- Gmail blue-link override (`u + .body a`)
- Samsung Mail link override
- `@media` query: full-width containers, 2-col and 3-col stacking, fluid images
- Visibility utilities: `hidden-sm`, `show-sm`
- Dark mode stub (commented out — opt-in)

```php
$email->body->setCSS($sheet->responsiveCss(breakpoint: 620));
```

---

## Preset components

All presets are static factories that return fully configured node trees. Use `deepclone()` to reuse a preset multiple times.

### `Spacer`

```php
use Rlnks\MailTree\Preset\Spacer;

$frame->gap1 = Spacer::make('20px');
$frame->gap2 = Spacer::make(sheet: $sheet);   // uses sheet's spacerHeight
```

Every spacer sets `font-size`, `line-height`, and `height` to the same value + `mso-line-height-rule: exactly` — the only reliable way to enforce pixel-perfect height in Outlook.

### `Divider`

```php
use Rlnks\MailTree\Preset\Divider;

$frame->rule = Divider::make();
$frame->rule = Divider::make(color: '#cccccc', width: '2px', paddingY: '20px');
$frame->rule = Divider::make(sheet: $sheet);
```

Renders a `border-bottom` on a collapsed `<td>` (`font-size:0; line-height:0; height:0`) — the only approach that works reliably across Outlook, Gmail, and Apple Mail.

### `Button` — VML hybrid

```php
use Rlnks\MailTree\Preset\Button;

$section->body->cta = Button::make('Subscribe', 'https://example.com');
$section->body->cta = Button::make('Buy now', $url,
    bgColor:      '#e63946',
    width:        240,
    height:       52,
    borderRadius: '26px',   // pill shape
    sheet:        $sheet,
);
```

**What it generates:**
- **Outlook 2007–2019 / 365:** `<v:roundrect>` VML shape with `arcsize` derived from `border-radius ÷ shorter-dimension`. The entire colored shape is clickable (`href` on the roundrect).
- **All other clients:** `<a>` with `display:inline-block`, `background-color`, `border-radius`, and explicit `line-height` for height control.

VML arcsize formula: `round(borderRadiusPx / min(width, height) * 100)`, capped at 50.

### `FullWidthImage`

```php
use Rlnks\MailTree\Preset\FullWidthImage;

$frame->hero = FullWidthImage::make($src, 'Hero image');
$frame->hero = FullWidthImage::make($src, $alt, href: 'https://…', sheet: $sheet);
```

No margin columns — image runs edge-to-edge. Uses `width="600"` HTML attribute alongside CSS `width:100%` so Outlook (which ignores `max-width`) still constrains the image. `display:block` eliminates the 4px baseline gap beneath inline images.

### `Section` — 1 column with margins

```php
use Rlnks\MailTree\Preset\Section;

$s = Section::make(sheet: $sheet);
$s->body->title = new Text('Hello!', 'h1');
$s->body->desc  = new Text('Body copy.', 'div');
$s->body->cta   = Button::make('Go', $url, sheet: $sheet);
$s->setStyle(['container' => ['background-color' => '#f9f9f9']]);
```

Structure: `Container(600px) > Column(30px) + Column(540px, section-body) + Column(30px)`.  
The `section-body` class is targeted in `responsiveCss()` for full-width on mobile.

### `TwoColumn` — responsive

```php
use Rlnks\MailTree\Preset\TwoColumn;

$row = TwoColumn::make(sheet: $sheet);
$row->left->img    = new Image($src1, $alt1);
$row->right->title = new Text('Feature name', 'h2');
$row->right->desc  = new Text('Description…', 'div');

// Add gutter between columns via padding
$row->left->setStyle(['column'  => ['padding-right' => '10px']]);
$row->right->setStyle(['column' => ['padding-left'  => '10px']]);
```

Columns tagged `col-2` → `display:block; width:100%` on mobile via `responsiveCss()`.

### `ThreeColumn` — responsive

```php
use Rlnks\MailTree\Preset\ThreeColumn;

$row = ThreeColumn::make(sheet: $sheet);
$row->col1->img = new Image($src1, '');
$row->col2->img = new Image($src2, '');
$row->col3->img = new Image($src3, '');
```

Columns tagged `col-3` → stack to full-width on mobile. If `(containerWidth − margins)` is not divisible by 3, the remainder pixels go to `col1` to avoid sub-pixel Outlook overflow.

---

## Style cascade

Styles flow **top-down** through the tree at render time. Each node receives its parent's full style array and merges its own overrides on top.

```php
$email = new EmailDocument([
    'text' => ['font-size' => '14px', 'color' => '#333'],
    'h1'   => ['font-size' => '28px', 'color' => '#c00'],
]);

// This node overrides h1 color only — font-size still inherited
$section->body->title = new Text('Hello', 'h1', ['h1' => ['color' => '#00c']]);
```

Style arrays are always **keyed by element type**:

```php
new Container(['container' => ['background-color' => '#fff', 'width' => '600px']]);
new Column   (['column'    => ['width' => '540px', 'padding' => '0 10px']]);
new Text('…', 'div', ['div' => ['font-size' => '16px']]);
new Image($src, $alt, ['img' => ['max-width' => '200px']]);
new Anchor($href,     ['a'   => ['color' => '#c00']]);
```

---

## deepclone

The `deepclone()` helper (auto-loaded globally) deep-copies any node tree. Use it to safely reuse pre-built blocks:

```php
$spacer = Spacer::make('20px');

$frame->before_header = deepclone($spacer);
$frame->after_header  = deepclone($spacer);
$frame->before_footer = deepclone($spacer);
```

---

## API reference

### `EmailDocument`

| Method | Description |
|---|---|
| `__construct(array $style = [])` | Initialize. Use `$sheet->emailStyle()` as argument. |
| `setSubject(string $title)` | `<title>` tag and email subject |
| `addLink(string $href, string $rel)` | `<link>` in `<head>` (Google Fonts, etc.) |
| `setStyle(array $style)` | Merge additional styles |
| `getStyle(): array` | Full resolved style array |
| `build(): string` | Render the complete HTML document |

### `Body`

| Method | Description |
|---|---|
| `__construct(array $style = [])` | Style overrides for `<body>` |
| `setCSS(string $css)` | Inject `<style>` block — use `$sheet->responsiveCss()` |

### `Container`

Renders as `<table><tbody><tr>`. Children should be `Column` instances.

| Method | Description |
|---|---|
| `__construct(array $style = [], bool $mso = false)` | `mso: true` wraps with Outlook conditional comments |
| `setClass(string $class)` | CSS class on `<table>` |
| `setID(string $id)` | id on `<table>` |
| `setStyle(array $style)` | Merge style overrides |

### `Column`

Renders as `<td>`.

| Method | Description |
|---|---|
| `__construct(array $style = [])` | `['column' => [...]]` |
| `setClass(string $class)` | CSS class on `<td>` (used for responsive: `col-2`, `col-3`, `section-body`) |
| `setStyle(array $style)` | Merge style overrides |

### `Text`

Wraps content in any inline or block tag. With `$tag = null` acts as a transparent wrapper.

| Constructor | |
|---|---|
| `new Text(string $text, ?string $tag = null, array $style = [])` | `$tag`: `h1`, `h2`, `h3`, `div`, `p`, `span`, etc. |

### `Image`

| Method | Description |
|---|---|
| `__construct(string $src, string $alt, array $style = [])` | |
| `setSrc(string $src, ?string $alt = null)` | Update after construction |

Emits `width`/`height` HTML attributes only when the CSS value is a plain integer (not `%`, `auto`).

### `Anchor`

| Method | Description |
|---|---|
| `__construct(string $href = '#', array $style = [])` | |
| `setLink(string $href)` | Update href |

---

## Email HTML best practices applied

Every class and preset follows current best practices:

- `border-collapse: collapse` + `border-spacing: 0` + `mso-table-lspace/rspace: 0pt` on all tables
- `cellpadding="0" cellspacing="0"` on all `<table>` elements
- `table-layout: fixed` prevents cell width negotiation in Outlook
- `display: block` on `<img>` eliminates the 4px baseline gap
- Numeric `width`/`height` HTML attributes on images alongside CSS (Outlook ignores `max-width`)
- Spacers: `font-size`, `line-height`, `height` all equal + `mso-line-height-rule: exactly`
- Dividers: `border-bottom` on `<td>` with collapsed font/line/height
- Buttons: VML `<v:roundrect>` for Outlook + `inline-block <a>` for all others
- Responsive: `@media` query + `display: block` column stacking
- Client resets: Outlook.com ExternalClass, Apple data detectors, Gmail `u + .body a`, Samsung Mail
- Dark mode: CSS stub provided in `responsiveCss()`

---

## Using as a back-end for a visual builder

Because the tree is plain PHP objects, it maps naturally to a JSON payload from a drag-and-drop front-end:

```php
function buildFromJson(array $node): Renderable {
    return match($node['type']) {
        'section'    => tap(Preset\Section::make(),    fn($s) => buildChildren($s->body, $node['children'] ?? [])),
        'two-column' => tap(Preset\TwoColumn::make(), fn($s) => buildChildren($s, $node['children'] ?? [])),
        'text'       => new Text($node['content'], $node['tag'] ?? null, $node['style'] ?? []),
        'image'      => new Image($node['src'], $node['alt'] ?? ''),
        'button'     => Preset\Button::make($node['label'], $node['href']),
        'spacer'     => Preset\Spacer::make($node['height'] ?? '20px'),
        default      => throw new \InvalidArgumentException("Unknown node: {$node['type']}"),
    };
}
```

---

## License

MIT
