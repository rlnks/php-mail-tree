<?php

namespace Rlnks\MailTree;

class Translator
{
    /** Locales never included in toXml() or locales(). */
    private const PROTECTED = ['php'];

    private array  $data      = [];
    private array  $bindings  = [];
    private string $locale    = 'fr';

    public function __construct(
        array  $data       = [],
        private readonly string $open       = '{{',
        private readonly string $close      = '}}',
        private readonly string $phpSnippet = "<?php echo \$t('{key}'); ?>",
    ) {
        if ($data) {
            $this->load($data);
        }
    }

    // ── Data loading ──────────────────────────────────────────────────────────

    /** Load a full translations array (as returned by a translations file). */
    public function load(array $data): static
    {
        foreach ($data as $key => $values) {
            $this->define($key, $values);
        }
        return $this;
    }

    /** Define or extend a single key with its locale values. */
    public function define(string $key, array $values): static
    {
        $this->data[$key] = array_merge($this->data[$key] ?? [], $values);
        return $this;
    }

    // ── Runtime bindings ──────────────────────────────────────────────────────

    /**
     * Bind a runtime value for a placeholder — same across all locales.
     * Takes priority over translations for every non-php locale.
     */
    public function bind(string $key, string $value): static
    {
        $this->bindings[$key] = $value;
        return $this;
    }

    /** Bind multiple runtime values at once. */
    public function bindMany(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->bind($key, (string) $value);
        }
        return $this;
    }

    // ── Locale ────────────────────────────────────────────────────────────────

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    // ── Resolution ────────────────────────────────────────────────────────────

    /**
     * Resolve a single key for a locale.
     *
     * - Runtime binding (bind/bindMany) wins for every locale except 'php'.
     * - 'tags' built-in: returns the placeholder unchanged ({{key}}).
     * - 'php'  built-in: returns the 'php' entry from data, or the configured
     *   phpSnippet with {key} substituted.
     * - Any other locale: returns the stored value (even empty string); if the
     *   key is absent for that locale the placeholder is returned unchanged.
     */
    public function get(string $key, ?string $locale = null): string
    {
        $locale ??= $this->locale;

        // Bindings win for all real locales (not php — php is code generation)
        if ($locale !== 'php' && array_key_exists($key, $this->bindings)) {
            return $this->bindings[$key];
        }

        if ($locale === 'tags') {
            return $this->open . $key . $this->close;
        }

        if ($locale === 'php') {
            return $this->data[$key]['php']
                ?? str_replace('{key}', $key, $this->phpSnippet);
        }

        // Regular locale: value exists (even '') → return it; absent → keep tag
        if (isset($this->data[$key]) && array_key_exists($locale, $this->data[$key])) {
            return $this->data[$key][$locale];
        }

        return $this->open . $key . $this->close;
    }

    /**
     * Replace all {{key}} placeholders in $html for the given locale.
     *
     * 'tags' mode is identity — the HTML is returned unchanged.
     * Null locale falls back to the locale set via setLocale().
     *
     * $passes controls how many substitution rounds are run. Two passes
     * are needed when a translated value itself contains a binding placeholder
     * — e.g. 'confirm_desc' in French is "Bonjour {{client_name}}…", so pass 1
     * replaces {{confirm_desc}} and pass 2 then resolves {{client_name}}.
     */
    public function resolve(string $html, ?string $locale = null, int $passes = 2): string
    {
        $locale ??= $this->locale;

        if ($locale === 'tags') {
            return $html;
        }

        $pattern = '/'
            . preg_quote($this->open,  '/')
            . '(\w+)'
            . preg_quote($this->close, '/')
            . '/';

        for ($i = 0; $i < $passes; $i++) {
            $html = preg_replace_callback(
                $pattern,
                fn(array $m): string => $this->get($m[1], $locale),
                $html,
            );
        }

        return $html;
    }

    // ── Inspection ────────────────────────────────────────────────────────────

    /**
     * Return all registered locale names, excluding protected ones (php).
     * Includes any user-defined custom locales found in the data.
     */
    public function locales(): array
    {
        $found = [];
        foreach ($this->data as $values) {
            foreach (array_keys($values) as $locale) {
                if (!in_array($locale, self::PROTECTED, true)) {
                    $found[$locale] = true;
                }
            }
        }
        return array_keys($found);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /**
     * Export translations to an XML string.
     *
     * Protected locales (php) are always excluded, even when explicitly listed.
     * If $locales is empty, all non-protected locales are included.
     * Only keys that have at least one value for the requested locales are emitted.
     */
    public function toXml(array $locales = []): string
    {
        $locales = $locales === []
            ? $this->locales()
            : array_values(array_diff($locales, self::PROTECTED));

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<translations>'];

        foreach ($this->data as $key => $values) {
            $lines[] = sprintf('    <string key="%s">', $e($key));
            foreach ($locales as $locale) {
                if (array_key_exists($locale, $values)) {
                    $lines[] = sprintf(
                        '        <value locale="%s">%s</value>',
                        $e($locale),
                        $e($values[$locale]),
                    );
                }
            }
            $lines[] = '    </string>';
        }

        $lines[] = '</translations>';
        return implode("\n", $lines) . "\n";
    }
}
