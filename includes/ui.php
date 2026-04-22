<?php
/**
 * RIESTE UI-Helpers for Fega-Lagerstand (Phase 2b).
 *
 * PHP 7.0-kompatible Funktionen, die die gleichen Komponenten rendern
 * wie die Jinja-Makros in Auth/IM und die React-Komponenten in
 * JTL/Controlling. Alle Klassen stammen aus design-reset.css bzw.
 * /design.css des Auth-Portals.
 *
 * API ist bewusst eng gehalten: args immer als array $options = [].
 *
 * Alle Render-Funktionen geben den fertigen HTML-String zurueck —
 * Aufrufer entscheiden ob sie echo oder in einen String puffern.
 */

if (!function_exists('rieste_esc')) {
    function rieste_esc($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rieste_button')) {
    /**
     * Render a button (or anchor if 'href' is set).
     *
     * Options:
     *   variant: 'primary' (default) | 'secondary' | 'ghost'
     *   icon:    SVG path d="..." (optional)
     *   href:    string (renders <a>)
     *   type:    submit|button|reset (default button)
     *   disabled: bool
     *   title:   tooltip string
     *   extra_class: additional class names
     */
    function rieste_button($label, $options = array()) {
        $variant = isset($options['variant']) ? $options['variant'] : 'primary';
        $icon = isset($options['icon']) ? $options['icon'] : null;
        $href = isset($options['href']) ? $options['href'] : null;
        $type = isset($options['type']) ? $options['type'] : 'button';
        $disabled = !empty($options['disabled']);
        $title = isset($options['title']) ? $options['title'] : null;
        $extra = isset($options['extra_class']) ? $options['extra_class'] : '';

        $cls = 'rieste-btn rieste-btn-' . rieste_esc($variant);
        if ($extra !== '') $cls .= ' ' . rieste_esc($extra);

        $iconHtml = '';
        if ($icon !== null) {
            $iconHtml = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . rieste_esc($icon) . '"/></svg>';
        }

        $titleAttr = $title ? ' title="' . rieste_esc($title) . '"' : '';
        $labelEsc = rieste_esc($label);

        if ($href !== null) {
            return '<a href="' . rieste_esc($href) . '" class="' . $cls . '"' . $titleAttr . '>' .
                   $iconHtml . '<span>' . $labelEsc . '</span></a>';
        }
        $disabledAttr = $disabled ? ' disabled' : '';
        return '<button type="' . rieste_esc($type) . '" class="' . $cls . '"' . $titleAttr . $disabledAttr . '>' .
               $iconHtml . '<span>' . $labelEsc . '</span></button>';
    }
}

if (!function_exists('rieste_input')) {
    /**
     * Render a labelled input field.
     *
     * Options:
     *   type, value, label, placeholder, required, disabled, help_text
     */
    function rieste_input($name, $options = array()) {
        $type = isset($options['type']) ? $options['type'] : 'text';
        $value = isset($options['value']) ? $options['value'] : '';
        $label = isset($options['label']) ? $options['label'] : null;
        $placeholder = isset($options['placeholder']) ? $options['placeholder'] : null;
        $required = !empty($options['required']);
        $disabled = !empty($options['disabled']);
        $help = isset($options['help_text']) ? $options['help_text'] : null;

        $id = 'f-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);

        $html = '<div class="rieste-field">';
        if ($label !== null) {
            $html .= '<label for="' . rieste_esc($id) . '" class="rieste-field-label">' . rieste_esc($label);
            if ($required) {
                $html .= ' <span aria-hidden="true" style="color: var(--color-error-text);">*</span>';
            }
            $html .= '</label>';
        }
        $attrs = ' id="' . rieste_esc($id) . '"';
        $attrs .= ' name="' . rieste_esc($name) . '"';
        $attrs .= ' type="' . rieste_esc($type) . '"';
        $attrs .= ' class="rieste-input"';
        $attrs .= ' value="' . rieste_esc($value) . '"';
        if ($placeholder !== null) $attrs .= ' placeholder="' . rieste_esc($placeholder) . '"';
        if ($required) $attrs .= ' required';
        if ($disabled) $attrs .= ' disabled';
        $html .= '<input' . $attrs . '>';
        if ($help !== null) {
            $html .= '<small class="rieste-field-help">' . rieste_esc($help) . '</small>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('rieste_select')) {
    /**
     * Render a select field. Options = array(array(value, label), ...).
     */
    function rieste_select($name, $options, $config = array()) {
        $selected = isset($config['selected']) ? $config['selected'] : '';
        $label = isset($config['label']) ? $config['label'] : null;
        $required = !empty($config['required']);

        $id = 'f-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);

        $html = '<div class="rieste-field">';
        if ($label !== null) {
            $html .= '<label for="' . rieste_esc($id) . '" class="rieste-field-label">' . rieste_esc($label) . '</label>';
        }
        $html .= '<select id="' . rieste_esc($id) . '" name="' . rieste_esc($name) . '" class="rieste-input"';
        if ($required) $html .= ' required';
        $html .= '>';
        foreach ($options as $opt) {
            list($val, $text) = $opt;
            $sel = ((string)$val === (string)$selected) ? ' selected' : '';
            $html .= '<option value="' . rieste_esc($val) . '"' . $sel . '>' . rieste_esc($text) . '</option>';
        }
        $html .= '</select></div>';
        return $html;
    }
}

if (!function_exists('rieste_card_start')) {
    /**
     * Rendered in two steps: rieste_card_start(...) + Inhalt + rieste_card_end().
     * Grund: PHP hat kein Caller-Macro-Pattern, also wird die Struktur
     * gepaart verwendet. In Views:
     *   echo rieste_card_start(array('title' => 'Foo'));
     *   // ...body html...
     *   echo rieste_card_end();
     */
    function rieste_card_start($options = array()) {
        $title = isset($options['title']) ? $options['title'] : null;
        $subtitle = isset($options['subtitle']) ? $options['subtitle'] : null;
        $extra = isset($options['extra_class']) ? $options['extra_class'] : '';

        $cls = 'rieste-card' . ($extra !== '' ? ' ' . rieste_esc($extra) : '');
        $html = '<div class="' . $cls . '">';
        if ($title !== null || $subtitle !== null) {
            $html .= '<div class="rieste-card-header"><div>';
            if ($title !== null) {
                $html .= '<div class="rieste-card-title">' . rieste_esc($title) . '</div>';
            }
            if ($subtitle !== null) {
                $html .= '<div class="rieste-card-subtitle">' . rieste_esc($subtitle) . '</div>';
            }
            $html .= '</div></div>';
        }
        $html .= '<div class="rieste-card-body">';
        return $html;
    }

    function rieste_card_end() {
        return '</div></div>';
    }
}

if (!function_exists('rieste_badge')) {
    /**
     * Options: variant = primary|success|warning|error|info|neutral
     */
    function rieste_badge($label, $options = array()) {
        $variant = isset($options['variant']) ? $options['variant'] : 'neutral';
        return '<span class="rieste-badge rieste-badge-' . rieste_esc($variant) . '">' .
               rieste_esc($label) . '</span>';
    }
}

if (!function_exists('rieste_stat_card')) {
    /**
     * Options: variant, sub, href, icon (SVG path d="")
     */
    function rieste_stat_card($value, $label, $options = array()) {
        $variant = isset($options['variant']) ? $options['variant'] : 'neutral';
        $sub = isset($options['sub']) ? $options['sub'] : null;
        $href = isset($options['href']) ? $options['href'] : null;
        $icon = isset($options['icon']) ? $options['icon'] : null;

        $tag = $href !== null ? 'a' : 'div';
        $attr = $href !== null ? ' href="' . rieste_esc($href) . '"' : '';

        $html = '<' . $tag . ' class="rieste-stat-card rieste-stat-card-' . rieste_esc($variant) . '"' . $attr . '>';
        if ($icon !== null) {
            $html .= '<div class="rieste-stat-card-icon">';
            $html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . rieste_esc($icon) . '"/></svg>';
            $html .= '</div>';
        }
        $html .= '<div class="rieste-stat-card-body">';
        $html .= '<div class="rieste-stat-card-value">' . rieste_esc($value) . '</div>';
        $html .= '<div class="rieste-stat-card-label">' . rieste_esc($label) . '</div>';
        if ($sub !== null) {
            $html .= '<div class="rieste-stat-card-sub">' . rieste_esc($sub) . '</div>';
        }
        $html .= '</div></' . $tag . '>';
        return $html;
    }
}

if (!function_exists('rieste_breadcrumbs')) {
    /**
     * $items = array(array('label' => '...', 'href' => '...' | null), ...)
     */
    function rieste_breadcrumbs($items) {
        $html = '<nav class="rieste-breadcrumbs" aria-label="Breadcrumb"><ol>';
        $last = count($items) - 1;
        foreach ($items as $i => $it) {
            $active = ($i === $last) ? ' active' : '';
            $html .= '<li class="rieste-breadcrumb-item' . $active . '">';
            if (!empty($it['href']) && $i !== $last) {
                $html .= '<a href="' . rieste_esc($it['href']) . '">' . rieste_esc($it['label']) . '</a>';
            } else {
                $html .= rieste_esc($it['label']);
            }
            $html .= '</li>';
        }
        $html .= '</ol></nav>';
        return $html;
    }
}
