/**
 * Repository-wide branding guard.
 *
 * Fails the build if retired product branding is reintroduced anywhere in the
 * source. The product is "Horas Trabalhadas" — never a "Pro" edition of it, and
 * never one of the old English names.
 *
 * Run with `npm run check:branding`. CI runs it on every push.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

/** Directories never scanned. */
const SKIP_DIRS = new Set(['.git', 'node_modules', 'dist', 'vendor', '.github/workflows/cache']);

/** Binary-ish extensions with no meaningful text to scan. */
const SKIP_EXT = new Set(['.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.zip', '.mo', '.woff', '.woff2', '.ttf', '.eot']);

/**
 * Forbidden patterns.
 *
 * Written as regexes so that spacing, hyphens and casing variants are all
 * caught. Underscored identifiers such as the legacy `work_hours_pro` shortcode
 * are deliberately NOT matched: that tag is saved inside published post content
 * on live sites and must keep working, so it is an allowed compatibility
 * identifier rather than branding. See ALLOWED below.
 */
const FORBIDDEN = [
  { re: /work[\s-]*hours[\s-]+pro\b/i, label: 'Work Hours Pro' },
  { re: /working[\s-]*hours[\s-]+pro\b/i, label: 'Working Hours Pro' },
  { re: /workhorse[\s-]+pro\b/i, label: 'Workhorse Pro' },
  { re: /horas[\s-]+trabalhadas[\s-]+pro\b/i, label: 'Horas Trabalhadas Pro' },
  { re: /\bWHP_[A-Z]/, label: 'legacy WHP_ constant prefix' },
  { re: /\bwhp_(?:icon|shortcode|register_assets|enqueue_assets|plugin_init|activate|deactivate|load_textdomain|post_has_shortcode|use_debug_assets|resource_hints|non_blocking_font|defer_script)\b/, label: 'legacy whp_ function name' },
  { re: /ghp_[A-Za-z0-9]{16,}/, label: 'GitHub personal access token' },
  { re: /github_pat_[A-Za-z0-9_]{20,}/, label: 'GitHub fine-grained token' },
];

/**
 * Exact strings that are permitted despite resembling a forbidden pattern.
 *
 * Each entry documents WHY it must stay. These are internal WordPress
 * identifiers that appear in already-published content or in already-stored
 * options; renaming them would break live sites.
 */
const ALLOWED = [
  { text: 'work_hours_pro', why: 'legacy shortcode tag saved in published post content' },
  { text: 'work_hours_calculator', why: 'legacy shortcode alias saved in published post content' },
  { text: 'whp_version', why: 'legacy option name, read once by the 2.0.0 migration' },
  { text: 'whc_pro_saved_v1', why: 'legacy localStorage key, read once by the storage migration' },
  { text: 'whc_theme', why: 'legacy localStorage key, read once by the storage migration' },
  { text: 'whp_lang', why: 'legacy localStorage key, read once by the storage migration' },
];

const files = [];
(function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    const rel = path.relative(root, full).split(path.sep).join('/');
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name) || SKIP_DIRS.has(rel)) continue;
      walk(full);
    } else {
      if (SKIP_EXT.has(path.extname(entry.name).toLowerCase())) continue;
      files.push(rel);
    }
  }
})(root);

let failures = 0;
let scanned = 0;

for (const rel of files) {
  const content = fs.readFileSync(path.join(root, rel), 'utf8');
  scanned++;
  const lines = content.split('\n');

  lines.forEach((line, i) => {
    for (const { re, label } of FORBIDDEN) {
      if (!re.test(line)) continue;

      // Skip the guard's own definitions, and any documented allowance.
      if (rel === 'tools/check-branding.js') continue;
      if (ALLOWED.some(a => line.includes(a.text) && !new RegExp(re.source, re.flags).test(line.split(a.text).join('')))) continue;

      console.error(`${rel}:${i + 1}  forbidden branding "${label}"`);
      console.error(`    ${line.trim().slice(0, 160)}`);
      failures++;
    }
  });
}

console.log(`\nscanned ${scanned} files`);

if (failures > 0) {
  console.error(`\nFAIL: ${failures} occurrence(s) of retired branding or a leaked credential.`);
  console.error('The product name is "Horas Trabalhadas". If an identifier genuinely must');
  console.error('stay for backward compatibility, add it to ALLOWED in this file with a reason.');
  process.exit(1);
}

console.log('PASS: no retired branding and no leaked credentials found.');
