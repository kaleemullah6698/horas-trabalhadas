/**
 * Structural sanity checks that do not need a running WordPress.
 *
 * These catch the mistakes that only show up after a plugin is already
 * installed: a missing file, an unguarded PHP entry point, a class that the
 * autoloader cannot find, or a shortcode alias silently dropped.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
let failures = 0;

const fail = msg => { console.error('FAIL: ' + msg); failures++; };
const ok = msg => console.log('ok: ' + msg);

const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const exists = rel => fs.existsSync(path.join(root, rel));

/* 1. Every file the plugin needs at runtime. */
const required = [
  'horas-trabalhadas.php', 'uninstall.php', 'readme.txt',
  'includes/class-plugin.php', 'includes/class-assets.php', 'includes/class-shortcode.php',
  'includes/class-icons.php', 'includes/class-updater.php', 'includes/class-license.php',
  'includes/class-admin.php', 'includes/class-migrations.php',
  'templates/calculator.php', 'templates/admin-settings.php',
  'assets/css/styles.css', 'assets/css/styles.min.css',
  'assets/js/app.js', 'assets/js/app.min.js',
  'languages/horas-trabalhadas.pot',
];
const missing = required.filter(f => !exists(f));
if (missing.length) fail('missing required files: ' + missing.join(', '));
else ok(`all ${required.length} required files present`);

/* 2. Every PHP file must refuse direct access. A file that runs outside
      WordPress is a remote code execution surface. */
const phpFiles = [];
(function walk(dir, base = '') {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['node_modules', '.git', 'dist'].includes(e.name)) continue;
    const rel = base ? `${base}/${e.name}` : e.name;
    if (e.isDirectory()) walk(path.join(dir, e.name), rel);
    else if (e.name.endsWith('.php')) phpFiles.push(rel);
  }
})(root);

const unguarded = phpFiles.filter(f => {
  const src = read(f);
  // uninstall.php has its own, stricter guard.
  if (f === 'uninstall.php') return !src.includes('WP_UNINSTALL_PLUGIN');
  // Index silence files are inert by construction.
  if (src.trim().length < 60) return false;
  return !src.includes("defined( 'ABSPATH' )");
});
if (unguarded.length) fail('PHP files without a direct-access guard: ' + unguarded.join(', '));
else ok(`all ${phpFiles.length} PHP files guard against direct access`);

/* 3. Autoloader contract: every class referenced in the bootstrap must resolve
      to includes/class-<lowercase-hyphenated>.php. */
const classes = ['Plugin', 'Assets', 'Shortcode', 'Icons', 'Updater', 'License', 'Admin', 'Migrations'];
const unresolvable = classes.filter(c => {
  const file = 'includes/class-' + c.toLowerCase().replace(/_/g, '-') + '.php';
  if (!exists(file)) return true;
  return !new RegExp(`class\\s+${c}\\b`).test(read(file));
});
if (unresolvable.length) fail('classes the autoloader cannot resolve: ' + unresolvable.join(', '));
else ok(`all ${classes.length} classes resolve through the autoloader`);

/* 3b. Every class file must actually contain PHP variables. A generated or
       shell-processed file can lose every "$" to escaping and still balance its
       braces, which produces a file that parses as nonsense. This catches that. */
const varless = classes
  .map(c => 'includes/class-' + c.toLowerCase() + '.php')
  .filter(f => exists(f) && !/\$[a-zA-Z_]/.test(read(f)));
if (varless.length) fail('class files with no PHP variables (escaping damage?): ' + varless.join(', '));
else ok('all class files contain PHP variables');

/* 3c. The icon table must be complete: a truncated table renders blank icons. */
const icons = read('includes/class-icons.php');
const iconCount = (icons.match(/^\t{3}'[a-z0-9-]+' =>/gm) || []).length;
if (iconCount === 27) ok('icon table complete (27 icons)');
else fail(`icon table has ${iconCount} entries, expected 27`);

/* 4. Backward compatibility: the legacy shortcode tags must stay registered,
      because they are saved inside published post content on live sites. */
const shortcode = read('includes/class-shortcode.php');
for (const tag of ['horas_trabalhadas', 'work_hours_pro', 'work_hours_calculator']) {
  if (shortcode.includes(`'${tag}'`)) ok(`shortcode [${tag}] still registered`);
  else fail(`shortcode [${tag}] is no longer registered — this breaks published pages`);
}

/* 5. The legacy option and localStorage keys must still be readable by the
      migrations, or upgrading sites silently lose their settings and data. */
if (read('includes/class-migrations.php').includes('whp_version')) ok('legacy option migration retained');
else fail('the whp_version migration was removed — upgrading sites lose their marker');

if (read('assets/js/app.js').includes('whc_pro_saved_v1')) ok('legacy localStorage migration retained');
else fail('the localStorage migration was removed — visitors lose saved timesheets');

/* 6. The template must not reintroduce an h1: the WordPress page that embeds the
      shortcode already supplies the page heading. */
if (/<h1[\s>]/i.test(read('templates/calculator.php'))) fail('templates/calculator.php contains an <h1>');
else ok('calculator template contains no <h1>');

/* 7. No secret may ever be committed. */
const settingsPatterns = [/['"]ghp_[A-Za-z0-9]/, /Authorization:\s*(token|Bearer)\s+[A-Za-z0-9]/i];
const leaky = phpFiles.concat(['assets/js/app.js']).filter(f =>
  settingsPatterns.some(re => re.test(read(f)))
);
if (leaky.length) fail('possible credential in: ' + leaky.join(', '));
else ok('no hard-coded credentials in PHP or JS');

console.log('');
if (failures) {
  console.error(`FAIL: ${failures} structural problem(s).`);
  process.exit(1);
}
console.log('PASS: structure is valid.');
