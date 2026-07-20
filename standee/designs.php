<?php
/**
 * AK Menu System — Standee design catalogue (data-driven).
 *
 * The gallery is composed from two axes:
 *   - STYLE FAMILIES : genuinely different compositions (corner triangles,
 *                      diagonal ribbon, waves, menu-card, confetti, fine-dining
 *                      frame, memphis shapes, gradient mesh, QR seal, side bar,
 *                      rangoli corners, arch window). Each family has its OWN
 *                      background treatment + framing — not a recolour.
 *   - COLOR PALETTES : vibrant themes (red, teal, purple, cafe, black-gold …).
 *
 * A concrete design = one family + one curated palette, id like "corners-t1".
 * Each family only advertises the palettes that suit it, so the catalogue stays
 * tasteful while every card in the gallery looks structurally distinct.
 *
 * This file is DATA + resolvers only — no rendering, no output. The renderer
 * (standee/render.php) and the client API (api/standee.php) depend on the same
 * entrypoints: standee_designs(), standee_resolve($id), standee_families().
 */

if (!function_exists('standee_palettes')) {

    /**
     * Vibrant colour themes.
     *   c1     : primary brand colour (bold shapes / gradient start)
     *   c2     : deeper shade of c1  (gradient end / footer)
     *   accent : bright pop colour   (QR frame, dots, motifs, stars)
     *   onbg   : text colour on the coloured area (near-white)
     *   ink    : text colour on the light/white area (dark)
     * @return array<string,array>
     */
    function standee_palettes(): array
    {
        return [
            't1'  => ['name' => 'Classic Red',   'c1' => '#e63946', 'c2' => '#9d1a24', 'accent' => '#ffcf3f', 'onbg' => '#ffffff', 'ink' => '#3a1013'],
            't2'  => ['name' => 'Sunset Orange', 'c1' => '#f97316', 'c2' => '#b23c06', 'accent' => '#ffd166', 'onbg' => '#ffffff', 'ink' => '#3a2413'],
            't3'  => ['name' => 'Fresh Teal',    'c1' => '#0d9488', 'c2' => '#0a5c56', 'accent' => '#f5c542', 'onbg' => '#ffffff', 'ink' => '#0d3a37'],
            't4'  => ['name' => 'Royal Purple',  'c1' => '#7c3aed', 'c2' => '#4c1d95', 'accent' => '#fbbf24', 'onbg' => '#ffffff', 'ink' => '#2e1065'],
            't5'  => ['name' => 'Garden Green',  'c1' => '#16a34a', 'c2' => '#0f5f2e', 'accent' => '#facc15', 'onbg' => '#ffffff', 'ink' => '#123f26'],
            't6'  => ['name' => 'Deep Navy',     'c1' => '#22447a', 'c2' => '#12294d', 'accent' => '#e9c46a', 'onbg' => '#ffffff', 'ink' => '#132540'],
            't7'  => ['name' => 'Hot Pink',      'c1' => '#ec4899', 'c2' => '#9d174d', 'accent' => '#fde047', 'onbg' => '#ffffff', 'ink' => '#500724'],
            't8'  => ['name' => 'Cafe Brown',    'c1' => '#8a5a34', 'c2' => '#4a2c18', 'accent' => '#e6b980', 'onbg' => '#ffffff', 'ink' => '#3a2415'],
            't9'  => ['name' => 'Black Gold',    'c1' => '#2b2b2b', 'c2' => '#050505', 'accent' => '#d4af37', 'onbg' => '#ffffff', 'ink' => '#1a1a1a'],
            't10' => ['name' => 'Ocean Blue',    'c1' => '#2563eb', 'c2' => '#1a3a8f', 'accent' => '#f5c542', 'onbg' => '#ffffff', 'ink' => '#16305e'],
            't11' => ['name' => 'Magenta Pop',   'c1' => '#c026d3', 'c2' => '#701a75', 'accent' => '#fde047', 'onbg' => '#ffffff', 'ink' => '#4a044e'],
            't12' => ['name' => 'Indigo Night',  'c1' => '#4f46e5', 'c2' => '#2b2870', 'accent' => '#f0abfc', 'onbg' => '#ffffff', 'ink' => '#1e1b4b'],
        ];
    }

    /**
     * Style families. `style` is the composition key the engine switches on;
     * `palettes` is the curated list of theme ids offered for that family.
     * @return array<string,array>
     */
    function standee_families(): array
    {
        return [
            'corners'    => ['name' => 'Corner Triangles', 'style' => 'corners',    'group' => 'Bold',    'palettes' => ['t1', 't3', 't10', 't7']],
            'ribbon'     => ['name' => 'Diagonal Ribbon',  'style' => 'ribbon',     'group' => 'Bold',    'palettes' => ['t2', 't4', 't6']],
            'wave'       => ['name' => 'Wave Bands',       'style' => 'wave',       'group' => 'Playful', 'palettes' => ['t3', 't10', 't5']],
            'menucard'   => ['name' => 'Menu Card',        'style' => 'menucard',   'group' => 'Bold',    'palettes' => ['t1', 't11', 't12']],
            'confetti'   => ['name' => 'Confetti',         'style' => 'confetti',   'group' => 'Playful', 'palettes' => ['t2', 't7', 't5']],
            'finedining' => ['name' => 'Fine Dining',      'style' => 'finedining', 'group' => 'Elegant', 'palettes' => ['t9', 't6', 't8']],
            'memphis'    => ['name' => 'Memphis Shapes',   'style' => 'memphis',    'group' => 'Playful', 'palettes' => ['t4', 't2', 't3']],
            'mesh'       => ['name' => 'Gradient Mesh',    'style' => 'mesh',       'group' => 'Elegant', 'palettes' => ['t11', 't12', 't10']],
            'seal'       => ['name' => 'QR Seal',          'style' => 'seal',       'group' => 'Elegant', 'palettes' => ['t1', 't5', 't9']],
            'sidebar'    => ['name' => 'Side Bar',         'style' => 'sidebar',    'group' => 'Bold',    'palettes' => ['t6', 't8', 't4']],
            'rangoli'    => ['name' => 'Rangoli Corners',  'style' => 'rangoli',    'group' => 'Festive', 'palettes' => ['t1', 't2', 't11']],
            'arch'       => ['name' => 'Arch Window',      'style' => 'arch',       'group' => 'Elegant', 'palettes' => ['t5', 't8', 't12']],
        ];
    }

    /**
     * Full flat list of every available design (family × its curated palettes).
     * @return array<int,array{id:string,family:string,palette:string,family_name:string,palette_name:string,group:string}>
     */
    function standee_designs(): array
    {
        $out = [];
        $palettes = standee_palettes();
        foreach (standee_families() as $fid => $f) {
            foreach ($f['palettes'] as $tid) {
                if (!isset($palettes[$tid])) { continue; }
                $out[] = [
                    'id'           => $fid . '-' . $tid,
                    'family'       => $fid,
                    'palette'      => $tid,
                    'family_name'  => $f['name'],
                    'palette_name' => $palettes[$tid]['name'],
                    'group'        => $f['group'],
                ];
            }
        }
        return $out;
    }

    /** Total number of designs currently offered. */
    function standee_design_count(): int
    {
        $n = 0;
        foreach (standee_families() as $f) { $n += count($f['palettes']); }
        return $n;
    }

    /**
     * Resolve a design id (e.g. "corners-t1") to its family + palette defs.
     * @return array{id:string,family:array,palette:array}|null null when invalid.
     */
    function standee_resolve(string $id): ?array
    {
        if (!preg_match('/^([a-z]+)-(t\d{1,2})$/', $id, $m)) { return null; }
        $families = standee_families();
        $palettes = standee_palettes();
        if (!isset($families[$m[1]], $palettes[$m[2]])) { return null; }
        // Only allow palettes the family actually advertises.
        if (!in_array($m[2], $families[$m[1]]['palettes'], true)) { return null; }
        return [
            'id'      => $id,
            'family'  => $families[$m[1]] + ['id' => $m[1]],
            'palette' => $palettes[$m[2]] + ['id' => $m[2]],
        ];
    }
}
