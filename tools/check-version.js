/**
 * Version consistency guard.
 *
 * The plugin version is authoritative in exactly one place — the
 * HORAS_TRABALHADAS_VERSION constant — but it is mirrored into the plugin header
 * and readme.txt because WordPress reads those. This check proves all three
 * agree, and, when run in CI for a tag build, that the git tag agrees too.
 *
 * Usage:
 *   node tools/check-version.js            verify the files agree
 *   node tools/check-version.js v2.0.0     also verify the tag matches
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const main = fs.readFileSync(path.join(root, 'horas-trabalhadas.php'), 'utf8');
const readme = fs.readFileSync(path.join(root, 'readme.txt'), 'utf8');

const pick = (source, re, what) => {
  const m = source.match(re);
  if (!m) {
    console.error(`FAIL: could not find ${what}`);
    process.exit(1);
  }
  return m[1].trim();
};

const headerVersion = pick(main, /^\s*\*\s*Version:\s*(.+)$/m, 'the Version: plugin header');
const constVersion = pick(main, /define\(\s*'HORAS_TRABALHADAS_VERSION',\s*'([^']+)'\s*\)/, 'the HORAS_TRABALHADAS_VERSION constant');
const stableTag = pick(readme, /^Stable tag:\s*(.+)$/m, 'the Stable tag in readme.txt');

console.log(`plugin header : ${headerVersion}`);
console.log(`constant      : ${constVersion}`);
console.log(`readme.txt    : ${stableTag}`);

const semver = /^\d+\.\d+\.\d+$/;
let failed = false;

if (!semver.test(constVersion)) {
  console.error(`FAIL: "${constVersion}" is not a MAJOR.MINOR.PATCH version.`);
  failed = true;
}

if (headerVersion !== constVersion) {
  console.error(`FAIL: plugin header (${headerVersion}) does not match the constant (${constVersion}).`);
  failed = true;
}

if (stableTag !== constVersion) {
  console.error(`FAIL: readme.txt Stable tag (${stableTag}) does not match the constant (${constVersion}).`);
  failed = true;
}

const tagArg = process.argv[2];
if (tagArg) {
  const tagVersion = tagArg.replace(/^refs\/tags\//, '').replace(/^v/, '');
  console.log(`git tag       : ${tagVersion}`);
  if (tagVersion !== constVersion) {
    console.error(`FAIL: git tag (${tagVersion}) does not match the plugin version (${constVersion}).`);
    console.error('Bump the version in horas-trabalhadas.php and readme.txt before tagging.');
    failed = true;
  }
}

if (failed) process.exit(1);
console.log('\nPASS: version is consistent everywhere.');
