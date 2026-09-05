/**
 * Lightweight PHP sanity check for machines without a PHP interpreter.
 *
 * This is NOT a substitute for `php -l` — the CI workflow runs the real linter on
 * PHP 7.2 and 8.3. It exists so an obvious structural mistake is caught locally
 * instead of after a push. It walks each file character by character, tracking
 * PHP/HTML mode, single- and double-quoted strings, heredocs/nowdocs and both
 * comment styles, then verifies that braces, parentheses and brackets balance.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

const files = [];
(function walk(dir, base = '') {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['node_modules', '.git', 'dist'].includes(e.name)) continue;
    const rel = base ? `${base}/${e.name}` : e.name;
    if (e.isDirectory()) walk(path.join(dir, e.name), rel);
    else if (e.name.endsWith('.php')) files.push(rel);
  }
})(root);

let failures = 0;

for (const rel of files) {
  const src = fs.readFileSync(path.join(root, rel), 'utf8');
  const stack = [];
  const pairs = { '}': '{', ')': '(', ']': '[' };

  let i = 0;
  let inPhp = false;
  let line = 1;
  let error = null;

  const at = () => `${rel}:${line}`;

  while (i < src.length && !error) {
    const ch = src[i];
    if (ch === '\n') line++;

    if (!inPhp) {
      if (src.startsWith('<?php', i) || src.startsWith('<?=', i)) {
        inPhp = true;
        i += src.startsWith('<?php', i) ? 5 : 3;
        continue;
      }
      i++;
      continue;
    }

    // Leaving PHP mode.
    if (src.startsWith('?>', i)) { inPhp = false; i += 2; continue; }

    // Comments.
    if (src.startsWith('//', i) || ch === '#') {
      while (i < src.length && src[i] !== '\n' && !src.startsWith('?>', i)) i++;
      continue;
    }
    if (src.startsWith('/*', i)) {
      const end = src.indexOf('*/', i + 2);
      if (end === -1) { error = `unterminated block comment opened at ${at()}`; break; }
      for (let k = i; k < end; k++) if (src[k] === '\n') line++;
      i = end + 2;
      continue;
    }

    // Heredoc / nowdoc.
    const here = /^<<<[ \t]*(['"]?)([A-Za-z_][A-Za-z0-9_]*)\1\r?\n/.exec(src.slice(i));
    if (here) {
      const label = here[2];
      const close = new RegExp(`^[ \\t]*${label}\\b`, 'm');
      const rest = src.slice(i + here[0].length);
      const m = close.exec(rest);
      if (!m) { error = `unterminated heredoc <<<${label} at ${at()}`; break; }
      const consumed = here[0].length + m.index + m[0].length;
      for (let k = 0; k < consumed; k++) if (src[i + k] === '\n') line++;
      i += consumed;
      continue;
    }

    // Strings.
    if (ch === "'" || ch === '"') {
      const quote = ch;
      i++;
      while (i < src.length) {
        if (src[i] === '\\') { i += 2; continue; }
        if (src[i] === '\n') line++;
        if (src[i] === quote) { i++; break; }
        i++;
      }
      continue;
    }

    if (ch === '{' || ch === '(' || ch === '[') { stack.push({ ch, line }); i++; continue; }

    if (ch === '}' || ch === ')' || ch === ']') {
      const open = stack.pop();
      if (!open) { error = `unmatched "${ch}" at ${at()}`; break; }
      if (open.ch !== pairs[ch]) {
        error = `"${ch}" at ${at()} closes "${open.ch}" opened on line ${open.line}`;
        break;
      }
      i++;
      continue;
    }

    i++;
  }

  if (!error && stack.length) {
    const open = stack[stack.length - 1];
    error = `unclosed "${open.ch}" opened at ${rel}:${open.line}`;
  }

  if (error) {
    console.error('FAIL: ' + error);
    failures++;
  }
}

console.log(`checked ${files.length} PHP files`);
if (failures) {
  console.error(`\nFAIL: ${failures} file(s) look structurally broken. Run "php -l" for the real error.`);
  process.exit(1);
}
console.log('PASS: braces, parentheses, brackets, strings and heredocs all balance.');
console.log('Note: this is a structural check only. CI runs the real php -l on 7.2 and 8.3.');
