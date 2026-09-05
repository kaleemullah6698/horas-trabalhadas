/**
 * Minifier for the plugin's shipped assets.
 *
 * Run `npm run build` after editing assets/css/styles.css or assets/js/app.js to
 * regenerate the .min files that WordPress serves (the unminified sources are
 * used only when SCRIPT_DEBUG is on). No dependencies, and the transforms are
 * deliberately conservative so behaviour cannot change.
 *
 * Pass --check to verify the committed .min files match their sources without
 * writing anything; the CI workflow uses this so a stale build fails the run.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const check = process.argv.includes('--check');

function minifyCss(css) {
  const banner = (css.match(/^\/\*![\s\S]*?\*\//) || [''])[0].replace(/\s*\n\s*/g, ' ');
  return banner + css
    .replace(/\/\*[\s\S]*?\*\//g, '')   // comments
    .replace(/\s*\n\s*/g, '')           // newlines + indentation
    .replace(/\s{2,}/g, ' ')
    .replace(/\s*([{}:;,>])\s*/g, '$1')
    .replace(/;}/g, '}')
    .trim();
}

/**
 * Line-oriented JS minifier. It only removes whole comment lines and leading
 * indentation - it never rewrites tokens, string contents or spacing inside a
 * line, so no expression, regex literal or template string can be altered.
 * Surviving lines are joined with newlines, keeping ASI behaviour identical.
 */
function minifyJs(js) {
  const out = [];
  let inBlock = false;
  for (const raw of js.split('\n')) {
    const line = raw.trim();
    if (inBlock) {
      if (line.indexOf('*/') !== -1) inBlock = false;
      continue;
    }
    if (line === '') continue;
    if (line.startsWith('//')) continue;
    if (line.startsWith('/*')) {
      if (line.indexOf('*/') === -1) inBlock = true;
      continue;
    }
    out.push(line);
  }
  return out.join('\n');
}

const jobs = [
  ['assets/css/styles.css', 'assets/css/styles.min.css', minifyCss],
  ['assets/js/app.js', 'assets/js/app.min.js', minifyJs],
];

let stale = 0;
for (const [src, dest, fn] of jobs) {
  const input = fs.readFileSync(path.join(root, src), 'utf8');
  const output = fn(input);
  const destPath = path.join(root, dest);

  if (check) {
    const current = fs.existsSync(destPath) ? fs.readFileSync(destPath, 'utf8') : '';
    if (current !== output) {
      console.error(`STALE: ${dest} does not match ${src}. Run "npm run build" and commit the result.`);
      stale++;
    } else {
      console.log(`ok: ${dest} is up to date`);
    }
    continue;
  }

  fs.writeFileSync(destPath, output);
  const pct = (100 - (output.length / input.length) * 100).toFixed(1);
  console.log(`${dest}  ${input.length} -> ${output.length} bytes (-${pct}%)`);
}

if (stale > 0) process.exit(1);
