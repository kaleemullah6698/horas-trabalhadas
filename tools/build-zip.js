/**
 * Build the WordPress distribution package.
 *
 * The result is `dist/horas-trabalhadas.zip`, whose single top-level directory is
 * `horas-trabalhadas/`. That structure is what makes WordPress treat an update as
 * an update: if the folder inside the zip were named anything else (as GitHub's
 * auto-generated source zipball is — `owner-repo-a1b2c3d/`), WordPress would
 * install a SECOND copy of the plugin beside the existing one instead of
 * upgrading it.
 *
 * The archive is written here in pure Node rather than by shelling out to a zip
 * utility. That is deliberate: Windows PowerShell's Compress-Archive writes
 * entry names with backslash separators, which the ZIP specification forbids and
 * which some unzip implementations — including the one inside WordPress —
 * mishandle. Writing the archive directly guarantees forward slashes and byte-
 * identical output on Windows, macOS and the Linux CI runner alike.
 *
 * Development files listed in .distignore never enter the package.
 *
 * Usage: node tools/build-zip.js
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const root = path.resolve(__dirname, '..');
const slug = 'horas-trabalhadas';
const dist = path.join(root, 'dist');

/* ------------------------------------------------------------------ *
 * Minimal ZIP writer (deflate, no dependencies).
 * ------------------------------------------------------------------ */

const CRC_TABLE = (() => {
  const table = new Int32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    table[n] = c;
  }
  return table;
})();

function crc32(buf) {
  let c = -1;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}

/** DOS date/time, as the ZIP header format requires. */
function dosTime(date) {
  const year = Math.max(1980, date.getFullYear());
  return {
    time: (date.getHours() << 11) | (date.getMinutes() << 5) | (date.getSeconds() >> 1),
    date: ((year - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate(),
  };
}

function writeZip(entries, outPath) {
  const local = [];
  const central = [];
  let offset = 0;

  for (const entry of entries) {
    // Entry names are always forward-slash separated, per the ZIP specification.
    const name = Buffer.from(entry.name.split(path.sep).join('/'), 'utf8');
    const raw = entry.data;
    const deflated = zlib.deflateRawSync(raw, { level: 9 });
    // Store rather than deflate when compression does not help.
    const useDeflate = deflated.length < raw.length;
    const body = useDeflate ? deflated : raw;
    const method = useDeflate ? 8 : 0;
    const { time, date } = dosTime(entry.mtime);
    const crc = crc32(raw);

    const header = Buffer.alloc(30);
    header.writeUInt32LE(0x04034b50, 0);
    header.writeUInt16LE(20, 4);           // version needed
    header.writeUInt16LE(0x0800, 6);       // flags: UTF-8 names
    header.writeUInt16LE(method, 8);
    header.writeUInt16LE(time, 10);
    header.writeUInt16LE(date, 12);
    header.writeUInt32LE(crc, 14);
    header.writeUInt32LE(body.length, 18);
    header.writeUInt32LE(raw.length, 22);
    header.writeUInt16LE(name.length, 26);
    header.writeUInt16LE(0, 28);

    local.push(header, name, body);

    const dirEntry = Buffer.alloc(46);
    dirEntry.writeUInt32LE(0x02014b50, 0);
    dirEntry.writeUInt16LE(20, 4);         // version made by
    dirEntry.writeUInt16LE(20, 6);         // version needed
    dirEntry.writeUInt16LE(0x0800, 8);
    dirEntry.writeUInt16LE(method, 10);
    dirEntry.writeUInt16LE(time, 12);
    dirEntry.writeUInt16LE(date, 14);
    dirEntry.writeUInt32LE(crc, 16);
    dirEntry.writeUInt32LE(body.length, 20);
    dirEntry.writeUInt32LE(raw.length, 24);
    dirEntry.writeUInt16LE(name.length, 28);
    dirEntry.writeUInt32LE(0, 38);         // external attributes
    dirEntry.writeUInt32LE(offset, 42);

    central.push(dirEntry, name);
    offset += header.length + name.length + body.length;
  }

  const centralBuf = Buffer.concat(central);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(entries.length, 8);
  eocd.writeUInt16LE(entries.length, 10);
  eocd.writeUInt32LE(centralBuf.length, 12);
  eocd.writeUInt32LE(offset, 16);

  fs.writeFileSync(outPath, Buffer.concat([...local, centralBuf, eocd]));
}

/* ------------------------------------------------------------------ *
 * Collect the files that belong in the package.
 * ------------------------------------------------------------------ */

function exclusions() {
  const file = path.join(root, '.distignore');
  if (!fs.existsSync(file)) return [];
  return fs.readFileSync(file, 'utf8')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l && !l.startsWith('#'))
    .map(l => l.replace(/\/$/, ''));
}

const EXCLUDED = exclusions();
const isExcluded = rel => EXCLUDED.some(ex => rel === ex || rel.startsWith(ex + '/'));

const files = [];
(function walk(dir, base = '') {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
    const rel = base ? `${base}/${entry.name}` : entry.name;
    if (isExcluded(rel)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, rel);
    else files.push(rel);
  }
})(root);

/* Required files must be present, or the plugin will not run once installed. */
const required = [
  'horas-trabalhadas.php',
  'uninstall.php',
  'readme.txt',
  'includes/class-plugin.php',
  'includes/class-assets.php',
  'includes/class-shortcode.php',
  'includes/class-icons.php',
  'includes/class-updater.php',
  'includes/class-license.php',
  'includes/class-admin.php',
  'includes/class-migrations.php',
  'templates/calculator.php',
  'templates/admin-settings.php',
  'assets/css/styles.min.css',
  'assets/js/app.min.js',
  'languages/horas-trabalhadas.pot',
];

const missing = required.filter(f => !files.includes(f));
if (missing.length) {
  console.error('FAIL: the package is missing required files:\n  ' + missing.join('\n  '));
  process.exit(1);
}

fs.rmSync(dist, { recursive: true, force: true });
fs.mkdirSync(dist, { recursive: true });

const entries = files.map(rel => ({
  name: `${slug}/${rel}`,
  data: fs.readFileSync(path.join(root, rel)),
  mtime: fs.statSync(path.join(root, rel)).mtime,
}));

const zipPath = path.join(dist, `${slug}.zip`);
writeZip(entries, zipPath);

const size = (fs.statSync(zipPath).size / 1024).toFixed(1);
console.log(`built dist/${slug}.zip  (${size} KB, ${entries.length} files)`);
console.log(`top-level directory: ${slug}/`);
console.log(`required files present: ${required.length}/${required.length}`);
