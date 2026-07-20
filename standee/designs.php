<?php
/**
 * AK Menu System — Standee design catalogue (data-driven).
 *
 * The gallery is built from two independent axes:
 *   - LAYOUT templates  (how the poster is composed: bands, frames, badges…)
 *   - COLOR palettes    (vibrant themes: red, teal, purple, cafe, black-gold…)
 *
 * A concrete design is the cross-product of one layout and one palette and gets
 * a stable id like "l3-t7" (layout l3, theme t7). layouts × palettes = 100+.
 *
 * This file holds ONLY data + tiny resolvers — no rendering, no output. Both the
 * public renderer (standee/render.php) and the client API (api/standee.php)
 * depend on it, so adding a new layout or palette here instantly grows the
 * gallery everywhere with zero other changes.
 */

if (!function_exists('standee_palettes')) {

    /**
     * Vibrant colour themes. Each palette gives the renderer everything it needs:
     *   c1  : primary brand colour (gradient start / band colour)
     *   c2  : deeper shade of c1  (gradient end / shadow tone)
     *   accent : bright pop colour (QR frame, dots, rules)
     *   onbg : text colour that sits on the coloured area (usually near-white)
     *   ink  : text colour that sits on the light/white area (dark)
     *
     * @return array<string,array> keyed by theme id (t1, t2, …)
     */
    function standee_palettes(): array
    {
        return [
            't1'  => ['name' => 'Classic Red',  'c1' => '#e63946', 'c2' => '#a4161a', 'accent' => '#ffd166', 'onbg' => '#ffffff', 'ink' => '#2b2b2b'],
            't2'  => ['name' => 'Sunset Orange','c1' => '#fb8500', 'c2' => '#c1440e', 'accent' => '#ffd60a', 'onbg' => '#ffffff', 'ink' => '#3a2413'],
            't3'  => ['name' => 'Fresh Teal',   'c1' => '#0d9488', 'c2' => '#065f5b', 'accent' => '#5eead4', 'onbg' => '#ffffff', 'ink' => '#123c3a'],
            't4'  => ['name' => 'Royal Purple', 'c1' => '#7c3aed', 'c2' => '#4c1d95', 'accent' => '#c4b5fd', 'onbg' => '#ffffff', 'ink' => '#2e1065'],
            't5'  => ['name' => 'Garden Green', 'c1' => '#16a34a', 'c2' => '#14532d', 'accent' => '#bbf7d0', 'onbg' => '#ffffff', 'ink' => '#14432a'],
            't6'  => ['name' => 'Deep Navy',    'c1' => '#1d3557', 'c2' => '#0b1d33', 'accent' => '#a8dadc', 'onbg' => '#ffffff', 'ink' => '#14243a'],
            't7'  => ['name' => 'Hot Pink',     'c1' => '#ec4899', 'c2' => '#9d174d', 'accent' => '#fbcfe8', 'onbg' => '#ffffff', 'ink' => '#500724'],
            't8'  => ['name' => 'Cafe Brown',   'c1' => '#8a5a34', 'c2' => '#4a2c18', 'accent' => '#e6c9a8', 'onbg' => '#ffffff', 'ink' => '#3a2415'],
            't9'  => ['name' => 'Black Gold',   'c1' => '#2b2b2b', 'c2' => '#000000', 'accent' => '#d4af37', 'onbg' => '#ffffff', 'ink' => '#1a1a1a'],
            't10' => ['name' => 'Ocean Blue',   'c1' => '#2563eb', 'c2' => '#1e3a8a', 'accent' => '#93c5fd', 'onbg' => '#ffffff', 'ink' => '#16305e'],
            't11' => ['name' => 'Magenta Pop',  'c1' => '#c026d3', 'c2' => '#701a75', 'accent' => '#f5d0fe', 'onbg' => '#ffffff', 'ink' => '#4a044e'],
            't12' => ['name' => 'Indigo Night', 'c1' => '#4f46e5', 'c2' => '#312e81', 'accent' => '#a5b4fc', 'onbg' => '#ffffff', 'ink' => '#1e1b4b'],
        ];
    }

    /**
     * Layout templates. `style` is the composition key the renderer switches on;
     * `name_area` tells the renderer whether the restaurant name sits on the
     * coloured area (use onbg text) or on the light area (use ink text).
     *
     * @return array<string,array> keyed by layout id (l1, l2, …)
     */
    function standee_layouts(): array
    {
        return [
            'l1'  => ['name' => 'Top Band',       'style' => 'band-top',    'name_area' => 'band'],
            'l2'  => ['name' => 'Diagonal Split', 'style' => 'diagonal',    'name_area' => 'band'],
            'l3'  => ['name' => 'Full Colour',    'style' => 'full',        'name_area' => 'band'],
            'l4'  => ['name' => 'Side Bar',       'style' => 'sidebar',     'name_area' => 'light'],
            'l5'  => ['name' => 'Circle Badge',   'style' => 'circle',      'name_area' => 'light'],
            'l6'  => ['name' => 'Minimal',        'style' => 'minimal',     'name_area' => 'light'],
            'l7'  => ['name' => 'Festive',        'style' => 'festive',     'name_area' => 'band'],
            'l8'  => ['name' => 'Bottom Band',    'style' => 'band-bottom', 'name_area' => 'light'],
            'l9'  => ['name' => 'Gradient Frame', 'style' => 'frame',       'name_area' => 'light'],
            'l10' => ['name' => 'Ribbon Banner',  'style' => 'ribbon',      'name_area' => 'band'],
        ];
    }

    /**
     * Full flat list of every available design (layout × palette).
     * @return array<int,array{id:string,layout:string,palette:string,layout_name:string,palette_name:string}>
     */
    function standee_designs(): array
    {
        $out = [];
        foreach (standee_layouts() as $lid => $l) {
            foreach (standee_palettes() as $tid => $t) {
                $out[] = [
                    'id'           => $lid . '-' . $tid,
                    'layout'       => $lid,
                    'palette'      => $tid,
                    'layout_name'  => $l['name'],
                    'palette_name' => $t['name'],
                ];
            }
        }
        return $out;
    }

    /** Total number of designs currently offered. */
    function standee_design_count(): int
    {
        return count(standee_layouts()) * count(standee_palettes());
    }

    /**
     * Resolve a design id (e.g. "l3-t7") back to its layout + palette definitions.
     * @return array{id:string,layout:array,palette:array}|null null when invalid.
     */
    function standee_resolve(string $id): ?array
    {
        if (!preg_match('/^(l\d{1,2})-(t\d{1,2})$/', $id, $m)) { return null; }
        $layouts  = standee_layouts();
        $palettes = standee_palettes();
        if (!isset($layouts[$m[1]], $palettes[$m[2]])) { return null; }
        return [
            'id'      => $id,
            'layout'  => $layouts[$m[1]] + ['id' => $m[1]],
            'palette' => $palettes[$m[2]] + ['id' => $m[2]],
        ];
    }
}
