/**
 * Generate languages/horas-trabalhadas.pot from the PHP sources.
 *
 * The source strings are already Brazilian Portuguese — that is deliberate. It
 * guarantees a pt-BR interface even on a site where the .mo files fail to load,
 * which is the primary market. The POT therefore exists so the plugin can be
 * translated OUT of Portuguese into other languages; pt_BR itself needs no
 * translation file.
 *
 * No dependency on WP-CLI, so it runs anywhere Node runs.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const files = [];
(function walk(dir, base = '') {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['node_modules', '.git', 'dist', 'tools'].includes(e.name)) continue;
    const rel = base ? `${base}/${e.name}` : e.name;
    if (e.isDirectory()) walk(path.join(dir, e.name), rel);
    else if (e.name.endsWith('.php')) files.push(rel);
  }
})(root);

// Matches __( 'x', 'domain' ) and its esc_*/_e variants with a single-quoted string.
const CALL = new RegExp(
  '\\b(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|__|_e)\\(\\s*' +
  "'((?:[^'\\\\]|\\\\.)*)'" +
  "\\s*,\\s*'horas-trabalhadas'\\s*\\)",
  'g'
);

const entries = new Map();
for (const rel of files) {
  const src = fs.readFileSync(path.join(root, rel), 'utf8');
  src.split('\n').forEach((line, i) => {
    let m;
    CALL.lastIndex = 0;
    while ((m = CALL.exec(line)) !== null) {
      const msgid = m[1];
      if (!entries.has(msgid)) entries.set(msgid, []);
      entries.get(msgid).push(rel + ':' + (i + 1));
    }
  });
}

const version = fs.readFileSync(path.join(root, 'horas-trabalhadas.php'), 'utf8')
  .match(/define\(\s*'HORAS_TRABALHADAS_VERSION',\s*'([^']+)'/)[1];

let out = [
  '# Copyright (C) Horas Trabalhadas',
  '# This file is distributed under the GPL-2.0-or-later license.',
  'msgid ""',
  'msgstr ""',
  '"Project-Id-Version: Horas Trabalhadas ' + version + '\\n"',
  '"Report-Msgid-Bugs-To: \\n"',
  '"MIME-Version: 1.0\\n"',
  '"Content-Type: text/plain; charset=UTF-8\\n"',
  '"Content-Transfer-Encoding: 8bit\\n"',
  '"Plural-Forms: nplurals=2; plural=(n > 1);\\n"',
  '"X-Generator: tools/make-pot.js\\n"',
  '"X-Domain: horas-trabalhadas\\n"',
  '',
].join('\n');

const sorted = [...entries.entries()].sort((a, b) => a[0].localeCompare(b[0]));
for (const [msgid, refs] of sorted) {
  const text = msgid.split("\\'").join("'").split('"').join('\\"');
  out += '\n#: ' + refs.join(' ') + '\n';
  out += 'msgid "' + text + '"\n';
  out += 'msgstr ""\n';
}

fs.writeFileSync(path.join(root, 'languages/horas-trabalhadas.pot'), out);
console.log('wrote languages/horas-trabalhadas.pot with ' + entries.size + ' strings from ' + files.length + ' PHP files');
