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

## Structure-first pattern

Because nodes are just PHP object properties, you can **declare the email skeleton first, then fill in the content separately**. This separates layout decisions from copy decisions — exactly like drag-and-drop in a visual builder, but in code.

```php
// ── 1. Skeleton — declare every section up front ──────────────────────────
$email = new EmailDocument($sheet->emailStyle());
$email->body = new Body();
$email->body->setCSS($sheet->responsiveCss());

$email->body->banner   = FullWidthImage::make($heroSrc, 'Hero');
$email->body->gap1     = Spacer::make(sheet: $sheet);
$email->body->intro    = Section::make(sheet: $sheet);
$email->body->gap2     = Spacer::make(sheet: $sheet);
$email->body->features = TwoColumn::make(sheet: $sheet);
$email->body->divider  = Divider::make(sheet: $sheet);
$email->body->footer   = Section::make(sheet: $sheet);

// ── 2. Content — fill each section independently ──────────────────────────
$email->body->intro->body->title = new Text('Order confirmed!', 'h1');
$email->body->intro->body->desc  = new Text('Thanks for your purchase.', 'div');
$email->body->intro->body->cta   = Button::make('View order', $orderUrl, sheet: $sheet);

$email->body->features->left->img    = new Image($img1, 'Feature A');
$email->body->features->right->title = new Text('Feature A', 'h2');
$email->body->features->right->desc  = new Text('Best feature ever.', 'div');

$email->body->footer->body->copy = new Text('© 2025 Acme Corp', 'div');

// ── 3. Render ──────────────────────────────────────────────────────────────
echo $email->build();
```

**Why this matters:**
- You can comment out an entire section (`// $email->body->features = …`) without touching content code.
- Content writers and layout developers can work in different blocks of the same file.
- The skeleton reads like a wireframe — `banner → gap → intro → gap → features → divider → footer` — matching the mental model of a designer.
- Nodes assigned later (step 2) are still accessible via `->` property access because `HasChildren` stores them in an array under the hood. Order of assignment doesn't matter; render order is insertion order.

---

## Node management

Every node supports a full set of tree-management methods. All methods return `$this` (or the affected node) so they can be chained.

### Visibility — `hide()` / `show()`

A hidden node is completely skipped during rendering. It stays in the tree, so it can be revealed again or toggled conditionally.

```php
$email->body->promo = Section::make(sheet: $sheet);

if (!$user->hasPromoAccess()) {
    $email->body->promo->hide();
}

// Chainable at assignment time:
$email->body->notice = (new Text('Beta feature', 'div'))->hide();
```

### Reordering — `moveUp()` / `moveDown()` / `moveToIndex()`

Nodes track their parent automatically when assigned via `->`. Move methods operate on the parent's child list immediately.

```php
$email->body->intro    = Section::make(sheet: $sheet);
$email->body->features = TwoColumn::make(sheet: $sheet);
$email->body->cta      = Section::make(sheet: $sheet);

$email->body->features->moveUp();        // swap features above intro
$email->body->cta->moveToIndex(0);       // jump to first position
$email->body->cta->moveDown(2);          // multi-step shift
```

`moveUp`/`moveDown` accept an optional `$steps` argument (default `1`). Positions clamp at boundaries — no wrap-around.

### Relative positioning — `insertBefore()` / `insertAfter()`

Position a node relative to a named sibling instead of an absolute index.

```php
// Skeleton built in order A → B → C
$email->body->intro    = Section::make(sheet: $sheet);
$email->body->features = TwoColumn::make(sheet: $sheet);
$email->body->footer   = Section::make(sheet: $sheet);

// Inject a new divider between features and footer:
$email->body->divider = Divider::make(sheet: $sheet);
$email->body->divider->insertBefore('footer');

// Or move features after the footer:
$email->body->features->insertAfter('footer');
```

The sibling is identified by the **property name** it was assigned under.

### Lifecycle — `detach()` / `replaceWith()` / `duplicate()`

```php
// Remove a section from the tree entirely (returns the detached node):
$removed = $email->body->promo->detach();

// Swap a node with a new one, keeping the same key and position:
$email->body->intro->body->title->replaceWith(new Text('New Title', 'h1'));

// Deep-clone a node and insert it immediately after itself:
$copy = $email->body->card->duplicate();
$copy->body->title->replaceWith(new Text('Card 2', 'h2'));
// Duplicate key is auto-generated: "card_2", "card_3", …
```

### Inspection — `getChildren()`

Returns a snapshot of all direct children, keyed by their property name.

```php
foreach ($email->body->getChildren() as $key => $node) {
    if (!$node->isHidden()) {
        echo "$key is visible\n";
    }
}
```

> **Note:** `Image` supports `hide()`/`show()` but not the tree-manipulation methods (`move*`, `insertBefore/After`, `detach`, `replaceWith`, `duplicate`, `getChildren`), since it cannot have children.

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

## Complete example

A production-ready multi-file structure that demonstrates every major pattern at once: separated styles and translations, skeleton-first construction, images and links defined as named variables, a single `build()` call, and multiple output variants from that one render.

```
my-email/
├── email.php           ← main template (entry point)
├── styles.php          ← $sheet->define() — all named section styles
└── translations.php    ← return [...] — all translatable strings
```

---

### `styles.php` — named section styles

```php
<?php
// Runs in the same scope as email.php; $sheet is already created there.

$sheet->define('header', [
    'container' => ['background-color' => '#ffffff'],
    'img'       => ['max-width' => '160px', 'margin' => 'auto', 'display' => 'block'],
]);

$sheet->define('hero', [
    'container' => ['background-color' => '#003366'],
    'h1'        => ['color' => '#ffffff', 'font-size' => '32px', 'font-weight' => 'bold'],
    'div'        => ['color' => '#aac4ff', 'line-height' => '170%'],
]);

$sheet->define('intro', [
    'container' => ['background-color' => '#ffffff'],
    'h2'        => ['color' => '#003366'],
    'div'        => ['color' => '#444444', 'line-height' => '160%'],
]);

$sheet->define('feature', [
    'container' => ['background-color' => '#f9f9f9'],
    'h3'        => ['color' => '#003366', 'font-size' => '18px'],
    'div'        => ['color' => '#555555'],
]);

$sheet->define('footer', [
    'container' => ['background-color' => '#eeeeee'],
    'div'        => ['color' => '#888888', 'font-size' => '12px', 'text-align' => 'center'],
    'a'          => ['color' => '#888888', 'text-decoration' => 'underline'],
]);
```

---

### `translations.php` — translatable strings

```php
<?php
// Returns an associative array: key → [locale => value, …]
// Omit a locale to keep {{key}} intact for that language.
// 'php' key: override the generated PHP snippet for dynamic server-side values.

return [

    // ── Email meta ────────────────────────────────────────────────────────────
    'subject' => [
        'fr'  => 'Votre commande est confirmée!',
        'en'  => 'Your order is confirmed!',
        'php' => "<?php echo \$email->subject(); ?>",
    ],

    // ── Hero ──────────────────────────────────────────────────────────────────
    'hero_title' => [
        'fr'  => 'Commande confirmée!',
        'en'  => 'Order confirmed!',
    ],
    'hero_desc' => [
        'fr'  => 'Merci, {{client_name}}. Votre commande #{{order_id}} est en préparation.',
        'en'  => 'Thank you, {{client_name}}. Your order #{{order_id}} is being prepared.',
    ],

    // ── Intro ─────────────────────────────────────────────────────────────────
    'intro_title' => [
        'fr'  => 'Récapitulatif de votre commande',
        'en'  => 'Your order summary',
    ],
    'intro_desc' => [
        'fr'  => 'Vous trouverez ci-dessous le détail de votre commande. Total: <strong>{{order_total}}</strong>.',
        'en'  => 'Below you will find the details of your order. Total: <strong>{{order_total}}</strong>.',
    ],
    'intro_cta' => [
        'fr'  => 'Voir ma commande',
        'en'  => 'View my order',
    ],

    // ── Feature ───────────────────────────────────────────────────────────────
    'feature_title' => [
        'fr'  => 'Votre espace client',
        'en'  => 'Your customer portal',
    ],
    'feature_desc' => [
        'fr'  => 'Suivez l\'avancement de votre commande, téléchargez vos factures et gérez vos préférences en tout temps.',
        'en'  => 'Track your order, download invoices and manage your preferences at any time.',
    ],
    'feature_cta' => [
        'fr'  => 'Accéder à mon espace',
        'en'  => 'Go to my portal',
    ],

    // ── Footer ────────────────────────────────────────────────────────────────
    'legal' => [
        'fr'  => 'Vous recevez ce courriel suite à votre achat. Les offres sont sujettes à changement sans préavis.',
        'en'  => 'You received this email following your purchase. Offers are subject to change without notice.',
        'php' => "<?php echo \$config->legalText(\$lang); ?>",
    ],
    'unsub_text' => [
        'fr'  => 'Me désinscrire',
        'en'  => 'Unsubscribe',
    ],
    'powered_by' => [
        'fr'  => 'Propulsé par RLNKS',
        'en'  => 'Powered by RLNKS',
    ],

];
```

---

### `email.php` — the template

```php
<?php
use Rlnks\MailTree\Anchor;
use Rlnks\MailTree\Body;
use Rlnks\MailTree\EmailDocument;
use Rlnks\MailTree\Image;
use Rlnks\MailTree\StyleSheet;
use Rlnks\MailTree\Text;
use Rlnks\MailTree\Translator;
use Rlnks\MailTree\Preset\Button;
use Rlnks\MailTree\Preset\Divider;
use Rlnks\MailTree\Preset\Section;
use Rlnks\MailTree\Preset\Spacer;
use Rlnks\MailTree\Preset\TwoColumn;

// ── 1. Theme & named styles ───────────────────────────────────────────────────

$sheet = new StyleSheet([
    'primaryColor'   => '#003366',
    'textColor'      => '#444444',
    'bgColor'        => '#f0f0f0',
    'containerBg'    => '#ffffff',
    'fontFamily'     => 'Arial, sans-serif',
    'baseFontSize'   => '15px',
    'containerWidth' => 600,
    'marginWidth'    => 30,
]);

require __DIR__ . '/styles.php';   // populates $sheet with named styles

// ── 2. Translator & runtime data ──────────────────────────────────────────────

$t = new Translator(require __DIR__ . '/translations.php');
$t->bindMany([
    'client_name' => $customer->firstName,      // same value in every language
    'order_id'    => (string) $order->id,
    'order_total' => $order->formattedTotal(),
]);

// ── 3. Email skeleton ─────────────────────────────────────────────────────────

$email = new EmailDocument($sheet->emailStyle());
$email->setSubject('{{subject}}');
$email->addLink('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700', 'stylesheet');

$email->body = new Body();
$email->body->setCSS($sheet->responsiveCss());

$email->body->header  = Section::make(sheet: $sheet);
$email->body->gap1    = Spacer::make(sheet: $sheet);
$email->body->hero    = Section::make(sheet: $sheet);
$email->body->gap2    = Spacer::make(sheet: $sheet);
$email->body->intro   = Section::make(sheet: $sheet);
$email->body->gap3    = Spacer::make(sheet: $sheet);
$email->body->feature = TwoColumn::make(sheet: $sheet);
$email->body->gap4    = Spacer::make(sheet: $sheet);
$email->body->divider = Divider::make(sheet: $sheet);
$email->body->footer  = Section::make(sheet: $sheet);

// ── 4. Section styles ─────────────────────────────────────────────────────────

$email->body->header->setStyle($sheet->get('header'));
$email->body->hero->setStyle($sheet->get('hero'));
$email->body->intro->setStyle($sheet->get('intro'));
$email->body->feature->setStyle($sheet->get('feature'));
$email->body->footer->setStyle($sheet->get('footer'));

// ── 5. Images ─────────────────────────────────────────────────────────────────

$img_logo    = new Image(
    'https://cdn.example.com/logo.png',
    'Example Corp',
    ['img' => ['max-width' => '160px', 'margin' => 'auto', 'display' => 'block']],
);

$img_feature = new Image(
    'https://cdn.example.com/portal-preview.jpg',
    '{{feature_title}}',
    ['img' => ['width' => '100%', 'display' => 'block']],
);

// ── 6. Links ──────────────────────────────────────────────────────────────────

$link_logo  = new Anchor('https://example.com');
$link_unsub = new Anchor('https://example.com/unsubscribe?id={{order_id}}');
$link_rlnks = new Anchor('https://rlnks.com');

// ── 7. Assemble — insert images, links and text into the skeleton ─────────────

// Header — logo wrapped in a link
$link_logo->logo = $img_logo;
$email->body->header->body->logo = $link_logo;

// Hero — headline + description
$email->body->hero->body->title = new Text('{{hero_title}}', 'h1');
$email->body->hero->body->desc  = new Text('{{hero_desc}}',  'div');

// Intro — order summary + CTA button
$email->body->intro->body->title = new Text('{{intro_title}}', 'h2');
$email->body->intro->body->desc  = new Text('{{intro_desc}}',  'div');
$email->body->intro->body->cta   = Button::make(
    '{{intro_cta}}',
    'https://example.com/order/{{order_id}}',
    sheet: $sheet,
);

// Feature — two-column: image left, text + link right
$email->body->feature->left->img    = $img_feature;
$email->body->feature->right->title = new Text('{{feature_title}}', 'h3');
$email->body->feature->right->desc  = new Text('{{feature_desc}}',  'div');
$email->body->feature->right->cta   = Button::make(
    '{{feature_cta}}',
    'https://example.com/portal',
    sheet: $sheet,
);

// Footer — legal, unsubscribe link, powered-by
$email->body->footer->body->legal = new Text('{{legal}}', 'div');

$link_unsub->text = new Text('{{unsub_text}}', 'span');
$email->body->footer->body->unsub = $link_unsub;

$link_rlnks->text = new Text('{{powered_by}}', 'span');
$email->body->footer->body->brand = $link_rlnks;

// ── 8. Build once ─────────────────────────────────────────────────────────────
//
// The tree is fully assembled. build() walks it top-down exactly once.
// Placeholders ({{key}}) are left intact — resolve() handles them next.

$base = $email->build();

// ── 9. Render variants — all from the same $base ─────────────────────────────

// For external ESP / template systems — {{key}} tags left untouched
$version_tags = $t->resolve($base, 'tags');

// Static language versions — for batch-send to known recipients
$version_fr   = $t->resolve($base, 'fr');
$version_en   = $t->resolve($base, 'en');

// PHP/online version — dynamic server-side snippets for each placeholder
// Save this to a .php file; when executed it reads live data from the DB
$version_php  = $t->resolve($base, 'php');

// Export translations for review or handoff to a translation service
$xml = $t->toXml(['fr', 'en']);
file_put_contents(__DIR__ . '/translations.xml', $xml);
```

**What this demonstrates:**

- `styles.php` and `translations.php` are the single sources of truth for their respective concerns — touch one file to update every variant simultaneously.
- The skeleton (step 3) reads like a wireframe: `header → gap → hero → gap → intro → gap → feature → gap → divider → footer`.
- Images (`$img_logo`, `$img_feature`) and links (`$link_logo`, `$link_unsub`) are defined as named variables in their own blocks — easy to update centrally and reuse.
- `$email->build()` is called exactly **once**. The same HTML tree produces four output variants without rebuilding anything.
- `$t->resolve($base, 'tags')` is what you send to Mailchimp, Klaviyo, or any ESP that has its own merge-tag system — the `{{key}}` placeholders act as the handoff format.
- `$t->resolve($base, 'php')` produces a self-contained PHP file suitable for inclusion in a dynamic mailer that reads client data at send time.

---

## StyleSheet

`StyleSheet` is the central style brain of the email. It does two independent things:

1. **Theme → style arrays** — you store brand tokens once; generator methods translate them into the CSS property arrays that each node class expects.
2. **Named-style registry** — a `define` / `get` system that replaces repeating raw style arrays across your template.

---

### Vocabulary 1 — Theme variables (design tokens)

The StyleSheet constructor takes **design tokens**, not CSS properties. Tokens are PHP camelCase names that represent a brand concept rather than a specific CSS rule:

| Token | Default | What it represents |
|---|---|---|
| `primaryColor` | `#333333` | Brand accent (headings, links, buttons) |
| `textColor` | `#444444` | Body copy color |
| `bgColor` | `#f0f0f0` | Outer page/body background |
| `containerBg` | `#ffffff` | Inner email content background |
| `borderColor` | `#dddddd` | Dividers, borders |
| `fontFamily` | `Arial, …` | Font stack |
| `baseFontSize` | `14px` | Default paragraph size |
| `lineHeight` | `150%` | Default line height |
| `containerWidth` | `600` | Email width in px (integer) |
| `marginWidth` | `30` | Left/right gutter width in px |
| `spacerHeight` | `20px` | Default vertical spacer height |
| `buttonRadius` | `4px` | Button corner radius |
| `buttonHeight` | `50` | Button height in px (integer) |
| `buttonWidth` | `200` | Button width in px (integer) |
| `buttonFontSize` | `16px` | Button label size |

**Why camelCase tokens instead of CSS properties directly?**

A single token like `primaryColor` maps to CSS in *many different places* — the `color` of an `h1`, the `background-color` of a button, the `border-color` of a divider. If you stored CSS properties directly you'd have to repeat and keep them in sync. The token is defined once; each generator method puts it in the right property for the right context.

```php
$sheet = new StyleSheet([
    'primaryColor'   => '#e63946',
    'textColor'      => '#333333',
    'bgColor'        => '#f4f4f4',
    'containerBg'    => '#ffffff',
    'fontFamily'     => "Poppins, Arial, sans-serif",
    'baseFontSize'   => '15px',
    'lineHeight'     => '160%',
    'containerWidth' => 600,
    'marginWidth'    => 30,
]);
```

---

### Vocabulary 2 — Style keys (semantic node keys)

Style arrays throughout the library are always nested under a **semantic key** that identifies which node type or HTML element receives the styles. The value is a flat array of standard CSS properties (`background-color`, `font-size`, etc.).

```
'body'      → <body> element                (used by Body)
'container' → <table> element               (used by Container)
'column'    → <td> element                  (used by Column)
'text'      → base for all text elements    (cascades to every Text node)
'h1'/'h2'/… → tag-specific text overrides   (merged on top of 'text')
'div'/'p'/'span'/… → same as above
'img'       → <img> element                 (used by Image)
'a'         → <a> element                   (used by Anchor)
```

**Why `container` and `column` instead of `table` and `td`?**

Email HTML wraps a logical "container" in multiple elements (`<table><tbody><tr>`) and Outlook sometimes adds MSO conditional wrappers around it. Using the key `container` means you're styling the *concept* rather than a specific tag. It also avoids confusion with the CSS `table` and `td` display values. The library owns the mapping; you use the semantic vocabulary.

```php
// All CSS properties inside the keys are standard CSS, written exactly as in a stylesheet.
new Container([
    'container' => [
        'background-color' => '#ffffff',    // standard CSS — goes on the <table>
        'width'            => '600px',
        'border-collapse'  => 'collapse',
    ],
]);

new Column([
    'column' => [
        'width'   => '270px',               // standard CSS — goes on the <td>
        'padding' => '0 20px',
    ],
]);

new Text('Hello', 'h1', [
    'text' => ['font-family' => 'Arial'],   // base — applies to all tags in this node
    'h1'   => ['color' => '#e63946'],       // tag-specific — merged on top of 'text'
]);
```

---

### Generator methods — theme → style arrays

These methods read your tokens and produce a ready-to-use style array with the correct semantic keys and standard CSS properties. Pass the return value directly to the matching constructor.

**`emailStyle()`** — pass to `new EmailDocument()`

```php
$email = new EmailDocument($sheet->emailStyle());
```

Produces (for the theme above):

```php
[
    'body' => [
        'background-color'         => '#f4f4f4',   // ← from bgColor token
        'margin'                   => 0,
        '-webkit-text-size-adjust' => '100%',
    ],
    'text' => [
        'font-family' => 'Poppins, Arial, sans-serif',  // ← from fontFamily
        'font-size'   => '15px',                        // ← from baseFontSize
        'line-height' => '160%',                        // ← from lineHeight
        'color'       => '#333333',                     // ← from textColor
    ],
    'h1'  => ['font-size' => '28px', 'color' => '#e63946', …],  // ← from primaryColor
    'h2'  => ['font-size' => '22px', 'color' => '#e63946', …],
    'a'   => ['color' => '#e63946', 'text-decoration' => 'none'],
    // …
]
```

This style array becomes the *root* of the cascade — every child node in the tree inherits from it.

| Generator method | Pass to | What it produces |
|---|---|---|
| `emailStyle()` | `new EmailDocument(…)` | body bg, global font, heading + link colors |
| `containerStyle()` | `new Container(…)` | container key with width, bg, table resets |
| `outerContainerStyle()` | `new Container(…, mso: true)` | same + no bg (transparent outer wrapper) |
| `marginColumnStyle()` | `new Column(…)` | column key with gutter width |
| `bodyColumnStyle()` | `new Column(…)` | column key with inner content width |
| `twoColStyle()` | `new Column(…)` | column key with half inner width |
| `threeColStyle()` | `new Column(…)` | column key with one-third inner width |
| `spacerStyle($height)` | `new Column(…)` | font-size/line-height/height triple for Outlook |
| `buttonContainerStyle()` | `new Container(…)` | bg-color, border-radius, center alignment |

All generators accept an optional `$overrides` array so you can tweak one property without redefining the whole block:

```php
// Override just the background on an otherwise standard container:
$c = new Container($sheet->containerStyle([
    'container' => ['background-color' => '#fffbe6'],
]));
```

---

### Named-style registry

Store reusable style combinations under a name and apply them via `get()`. Keys inside the stored style follow the same semantic vocabulary (`container`, `column`, `h1`, `div`, etc.) and values are standard CSS properties.

```php
// ── Define once, usually at the top of your template ──────────────────────

$sheet->define('hero', [
    'container' => ['background-color' => '#003366'],   // standard CSS on the <table>
    'h1'        => ['color' => '#ffffff', 'font-size' => '32px'],
    'div'        => ['color' => '#aac4ff', 'line-height' => '170%'],
]);

$sheet->define('highlight', [
    'container' => [
        'background-color' => '#fff8e1',
        'border-left'      => '4px solid #e63946',
    ],
    'div' => ['color' => '#333333', 'padding' => '0 0 0 12px'],
]);

$sheet->define('footer', [
    'container' => ['background-color' => '#f0f0f0'],
    'div'       => ['color' => '#888888', 'font-size' => '12px', 'text-align' => 'center'],
    'a'         => ['color' => '#888888'],
]);

// ── Apply to sections ──────────────────────────────────────────────────────

$email->body->hero->setStyle($sheet->get('hero'));
$email->body->note->setStyle($sheet->get('highlight'));
$email->body->foot->setStyle($sheet->get('footer'));

// Retrieve with per-call overrides (does not modify the stored definition):
$email->body->alt_hero->setStyle($sheet->get('hero', [
    'h1' => ['font-size' => '24px'],   // smaller h1, everything else from 'hero'
]));

// Extend a definition in-place (modifies the stored definition):
$sheet->extend('hero', [
    'container' => ['border-bottom' => '3px solid #e63946'],
]);
```

| Method | Description |
|---|---|
| `define(string $name, array $style)` | Store a named style; returns `$this` for chaining |
| `get(string $name, array $overrides = [])` | Retrieve — optionally merged with one-off overrides |
| `extend(string $name, array $extra)` | Merge additional keys into an existing definition |
| `has(string $name)` | Check whether a name is registered |

---

### Shared styles file

The named-style registry is designed to live in a dedicated file you `require` at the top of each template — the PHP equivalent of a CSS stylesheet. This keeps all brand-specific colours, backgrounds, and typography rules out of your template logic.

**`my_styles.php`**

```php
<?php
// All section styles in one place.
// Semantic keys follow the library vocabulary: container, column, text, h1, div, img, a, …

$sheet->define('logo', [
    'img' => ['max-width' => '150px', 'margin' => 'auto', 'display' => 'block'],
]);

$sheet->define('hero', [
    'container' => ['background-color' => '#003366'],
    'h1'        => ['color' => '#ffffff', 'font-size' => '32px'],
    'div'        => ['color' => '#aac4ff', 'line-height' => '170%'],
]);

$sheet->define('promo', [
    'container' => ['background-color' => '#fff8e1'],
    'h2'        => ['color' => '#e63946'],
    'div'        => ['color' => '#333333'],
]);

$sheet->define('credits', [
    'container' => ['background-color' => '#f0f0f0'],
    'div'        => ['color' => '#888888', 'font-size' => '12px', 'text-align' => 'center'],
    'a'          => ['color' => '#888888'],
]);
```

**`email.php`**

```php
<?php
$sheet = new StyleSheet(['primaryColor' => '#e63946', /* … */]);
require 'my_styles.php';   // populates $sheet with all named styles

$email = new EmailDocument($sheet->emailStyle());
$email->body = new Body();
$email->body->setCSS($sheet->responsiveCss());

// ── Structure ──────────────────────────────────────────────────────────────
$email->body->logo    = Section::make(sheet: $sheet);
$email->body->hero    = Section::make(sheet: $sheet);
$email->body->promo   = Section::make(sheet: $sheet);
$email->body->credits = Section::make(sheet: $sheet);

// ── Styles — one line per section ─────────────────────────────────────────
$email->body->logo->setStyle($sheet->get('logo'));
$email->body->hero->setStyle($sheet->get('hero'));
$email->body->promo->setStyle($sheet->get('promo'));
$email->body->credits->setStyle($sheet->get('credits'));

// ── Content ────────────────────────────────────────────────────────────────
$email->body->logo->body->img      = new Image($logoSrc, 'Acme');
$email->body->hero->body->title    = new Text('Order confirmed!', 'h1');
$email->body->hero->body->desc     = new Text('Thanks for your purchase.', 'div');
$email->body->promo->body->title   = new Text('You may also like', 'h2');
$email->body->credits->body->copy  = new Text('© 2025 Acme Corp · Unsubscribe', 'div');

echo $email->build();
```

### `applyStyle()` — targeting specific children

When a named style needs to address a **specific named child** of a section rather than the section itself, use `applyStyle()` instead of `setStyle()`. The optional `'children'` key maps child property names to their own style arrays — other children are unaffected.

```php
$sheet->define('sectionPromo', [
    'container' => ['background-color' => '#003366'],   // applied to this node
    'children'  => [
        'body' => [                                      // applied to ->body only
            'column' => ['background-color' => '#00264d'],
        ],
    ],
]);

$email->body->promo->applyStyle($sheet->get('sectionPromo'));
```

All keys except `'children'` are applied to the node itself via `setStyle()`. The `'children'` map recurses — if a child also has `HasChildren`, its entry can contain another `'children'` key.

```php
// Chaining:
$email->body->hero
    ->applyStyle($sheet->get('hero'))
    ->applyStyle(['h2' => ['font-size' => '20px']]);   // one-off tweak
```

---

### `responsiveCss(int $breakpoint = 620)`

Returns a complete `<style>` string to inject via `$email->body->setCSS(…)`. It is the only place in the package that outputs a `<style>` block — everything else is inline CSS.

```php
$email->body->setCSS($sheet->responsiveCss());           // breakpoint: 620px (default)
$email->body->setCSS($sheet->responsiveCss(breakpoint: 480)); // tighter breakpoint
```

What it includes:

- **Client resets**: `body` margin/padding, Outlook.com `.ExternalClass`, global `table` collapse + `mso-table-lspace`
- **Image resets**: `display:block`, `border:0`, bicubic interpolation
- **Apple Mail**: kills auto-detected blue links (`a[x-apple-data-detectors]`)
- **Gmail**: kills blue link rewrite (`u + .body a`)
- **Samsung Mail**: kills link rewrite (`#MessageViewBody a`)
- **`@media` responsive**: `devicewidth` tables → 100%, `section-body` → 100%, `col-2`/`col-3` → `display:block`, fluid images, base font bump
- **Utilities**: `hidden-sm` (hide on mobile), `show-sm` (show only on mobile)
- **Dark mode stub**: commented-out `@media (prefers-color-scheme: dark)` block — opt-in, ready to uncomment

---

## Translator

`Translator` handles all text placeholder resolution — multi-language versions, template tag output, PHP snippet generation, and runtime variable injection. It works as a **post-processor**: write `{{key}}` in any text node, call `$email->build()` once, then resolve into as many output variants as needed.

```php
use Rlnks\MailTree\Translator;

$t = new Translator(require 'translations.php');
$t->setLocale('fr');                           // default locale

$base = $email->build();                       // HTML with {{...}} intact

$html_fr   = $t->resolve($base, 'fr');         // French text
$html_en   = $t->resolve($base, 'en');         // English text
$html_tags = $t->resolve($base, 'tags');       // placeholders untouched
$html_php  = $t->resolve($base, 'php');        // PHP snippets
```

---

### Translations file

A plain PHP file that returns a keyed array. Each key maps to an array of locale → value pairs. Only include the locales you actually have — a missing locale keeps the `{{key}}` placeholder intact in the output.

```php
// translations.php
return [
    'welcome_title' => [
        'fr' => 'Bienvenue!',
        'en' => 'Welcome!',
        // 'de' absent → {{welcome_title}} stays intact when rendering in German
    ],

    'cta_btn' => [
        'fr' => 'Voir ma commande',
        'en' => 'View my order',
    ],

    // 'php' key: override the generated PHP snippet for complex expressions
    'client_name' => [
        'fr'  => 'Client',                              // fallback for static renders
        'en'  => 'Client',
        'php' => '<?php echo htmlspecialchars($customer->firstName); ?>',
    ],

    // Custom locale: any name works — for external sending systems
    'unsub_link' => [
        'fr'         => 'Me désinscrire',
        'en'         => 'Unsubscribe',
        'mailchimp'  => '*|UNSUB|*',                    // Mailchimp merge tag
        'brevo'      => '{{ unsubscribe_link }}',       // Brevo / Sendinblue tag
    ],
];
```

Load it:
```php
$t = new Translator(require 'translations.php');
// or incrementally:
$t->load(require 'translations.php');
$t->define('extra_key', ['fr' => 'Extra', 'en' => 'Extra']);
```

---

### Using placeholders in nodes

Write `{{key}}` anywhere in a text string. Placeholders survive `build()` unchanged and are resolved by `resolve()`.

```php
$section->body->title = new Text('{{welcome_title}}', 'h1');
$section->body->desc  = new Text('Bonjour {{client_name}}, commande #{{order_id}}', 'div');
$section->body->cta   = Button::make('{{cta_btn}}', $url);
```

---

### Runtime values — `bind()`

`bind()` injects a value that applies to every locale (same across languages — client name, order ID, URL, etc.). Bindings take priority over translations for all non-`php` locales.

```php
$t->bind('client_name', $customer->firstName);
$t->bind('order_id',    (string) $order->id);

// Or in batch:
$t->bindMany([
    'client_name' => $customer->firstName,
    'order_id'    => (string) $order->id,
]);
```

---

### Built-in modes

| Mode | Behaviour |
|---|---|
| `'fr'`, `'en'`, any locale | Replace `{{key}}` with the stored translation; keep tag if absent |
| `'tags'` | Identity — HTML returned unchanged, all `{{key}}` intact |
| `'php'` | Replace `{{key}}` with `php` entry from data, or generated snippet |

**`php` mode** is designed for generating PHP template files (`.php` mail templates for other systems). It is **protected**: `php` entries never appear in `toXml()` output and `php` is excluded from `locales()`.

The default PHP snippet is configurable:
```php
$t = new Translator($data, phpSnippet: "<?php echo \$vars['{key}']; ?>");
// {{welcome_title}} → <?php echo $vars['welcome_title']; ?>
```

---

### XML export

`toXml()` produces an XML file of all translations — useful for handing off to a translation service or storing in a CMS. The `php` locale is always excluded.

```php
$xml = $t->toXml();               // all locales
$xml = $t->toXml(['fr', 'en']);   // specific locales only (php still excluded)
file_put_contents('translations.xml', $xml);
```

Output format:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<translations>
    <string key="welcome_title">
        <value locale="fr">Bienvenue!</value>
        <value locale="en">Welcome!</value>
    </string>
    <string key="cta_btn">
        <value locale="fr">Voir ma commande</value>
        <value locale="en">View my order</value>
    </string>
</translations>
```

---

### Complete workflow example

```php
// bootstrap.php ───────────────────────────────────────────────────────────────
$t = new Translator(require 'translations.php');
$t->bindMany([
    'client_name' => $customer->firstName,
    'order_id'    => (string) $order->id,
]);

// email.php — build once ──────────────────────────────────────────────────────
$email->body->intro->body->title = new Text('{{welcome_title}}', 'h1');
$email->body->intro->body->desc  = new Text('Bonjour {{client_name}}, commande #{{order_id}}.', 'div');
$email->body->intro->body->cta   = Button::make('{{cta_btn}}', $orderUrl);
$email->body->foot->body->unsub  = new Text('<a href="{{unsub_link}}">{{unsub_text}}</a>', 'div');

$base = $email->build();

// Render as many variants as needed ──────────────────────────────────────────
$html_fr  = $t->resolve($base, 'fr');         // send to French recipients
$html_en  = $t->resolve($base, 'en');         // send to English recipients
$template = $t->resolve($base, 'tags');        // {{...}} intact for other systems
$php_tmpl = $t->resolve($base, 'php');         // PHP template file output
$mc_tmpl  = $t->resolve($base, 'mailchimp');   // Mailchimp merge tags

// Export translations for review ──────────────────────────────────────────────
file_put_contents('translations.xml', $t->toXml(['fr', 'en']));
```

---

### API reference — `Translator`

| Method | Description |
|---|---|
| `__construct(array $data = [], string $open = '{{', string $close = '}}', string $phpSnippet = …)` | Initialize with optional data and delimiters |
| `load(array $data): static` | Merge a full translations array (from `require 'translations.php'`) |
| `define(string $key, array $values): static` | Define or extend a single key |
| `bind(string $key, string $value): static` | Runtime value — same across all locales, overrides translations |
| `bindMany(array $values): static` | Batch bind |
| `setLocale(string $locale): static` | Set the default locale for calls without explicit locale |
| `getLocale(): string` | Return the current default locale |
| `get(string $key, ?string $locale = null): string` | Resolve a single key |
| `resolve(string $html, ?string $locale = null): string` | Replace all placeholders in an HTML string |
| `locales(): array` | All registered locales excluding protected ones (`php`) |
| `toXml(array $locales = []): string` | Export to XML; `php` always excluded |

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

The methods below marked **†** are available on all nodes that can have children (`Body`, `Container`, `Column`, `Text`, `Anchor`). `Image` only supports `hide()`/`show()`.

### `Body`

| Method | Description |
|---|---|
| `__construct(array $style = [])` | Style overrides for `<body>` |
| `setCSS(string $css)` | Inject `<style>` block — use `$sheet->responsiveCss()` |
| `setStyle(array $style)` | Merge additional styles |
| `getStyle(): array` | Full resolved style array |
| `hide() / show()` | Toggle visibility in the rendered output |
| `moveUp(int $steps = 1)` † | Move earlier in parent's child list |
| `moveDown(int $steps = 1)` † | Move later in parent's child list |
| `moveToIndex(int $index)` † | Jump to absolute position (0 = first) |
| `insertBefore(string $key)` † | Reposition just before the named sibling |
| `insertAfter(string $key)` † | Reposition just after the named sibling |
| `detach()` † | Remove from parent; returns self for re-attachment |
| `replaceWith(Renderable $node)` † | Swap out in-place; returns the replaced node |
| `duplicate()` † | Clone + insert after self; returns the duplicate |
| `getChildren(): array` † | Snapshot of direct children keyed by property name |
| `applyStyle(array $style)` † | Apply semantic keys to self + optionally target named children via `'children'` key; returns `$this` |

### `Container`

Renders as `<table><tbody><tr>`. Children should be `Column` instances.

| Method | Description |
|---|---|
| `__construct(array $style = [], bool $mso = false)` | `mso: true` wraps with Outlook conditional comments |
| `setClass(string $class)` | CSS class on `<table>` |
| `setID(string $id)` | id on `<table>` |
| `setStyle(array $style)` | Merge style overrides |
| `getStyle(): array` | Full resolved style array |
| `hide() / show()` | Toggle visibility in the rendered output |
| `moveUp / moveDown / moveToIndex` † | See `Body` above |
| `insertBefore / insertAfter` † | See `Body` above |
| `detach / replaceWith / duplicate / getChildren` † | See `Body` above |
| `applyStyle(array $style)` † | See `Body` above |

### `Column`

Renders as `<td>`.

| Method | Description |
|---|---|
| `__construct(array $style = [])` | `['column' => [...]]` |
| `setClass(string $class)` | CSS class on `<td>` (used for responsive: `col-2`, `col-3`, `section-body`) |
| `setStyle(array $style)` | Merge style overrides |
| `getStyle(): array` | Full resolved style array |
| `hide() / show()` | Toggle visibility in the rendered output |
| `moveUp / moveDown / moveToIndex` † | See `Body` above |
| `insertBefore / insertAfter` † | See `Body` above |
| `detach / replaceWith / duplicate / getChildren` † | See `Body` above |
| `applyStyle(array $style)` † | See `Body` above |

### `Text`

Wraps content in any inline or block tag. With `$tag = null` acts as a transparent wrapper.

| Method | Description |
|---|---|
| `__construct(string $text, ?string $tag = null, array $style = [])` | `$tag`: `h1`, `h2`, `h3`, `div`, `p`, `span`, etc. |
| `setStyle(array $style)` | Merge style overrides |
| `getStyle(): array` | Full resolved style array |
| `hide() / show()` | Toggle visibility in the rendered output |
| `moveUp / moveDown / moveToIndex` † | See `Body` above |
| `insertBefore / insertAfter` † | See `Body` above |
| `detach / replaceWith / duplicate / getChildren` † | See `Body` above |
| `applyStyle(array $style)` † | See `Body` above |

### `Image`

| Method | Description |
|---|---|
| `__construct(string $src = '', string $alt = '', array $style = [])` | |
| `setSrc(string $src, ?string $alt = null)` | Update src (and optionally alt) after construction |
| `setStyle(array $style)` | Merge style overrides |
| `getStyle(): array` | Full resolved style array |
| `hide() / show()` | Toggle visibility in the rendered output |

Emits `width`/`height` HTML attributes only when the CSS value is a plain integer (not `%`, `auto`).

### `Anchor`

| Method | Description |
|---|---|
| `__construct(string $href = '#', array $style = [])` | |
| `setLink(string $href)` | Update href after construction |
| `setStyle(array $style)` | Merge style overrides |
| `getStyle(): array` | Full resolved style array |
| `hide() / show()` | Toggle visibility in the rendered output |
| `moveUp / moveDown / moveToIndex` † | See `Body` above |
| `insertBefore / insertAfter` † | See `Body` above |
| `detach / replaceWith / duplicate / getChildren` † | See `Body` above |
| `applyStyle(array $style)` † | See `Body` above |

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
