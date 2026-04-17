/**
 * Syncs semver into AppServiceProvider (NativeBlade desktop version) and src-tauri/tauri.conf.json.
 * Expects RELEASE_VERSION=1.2.3 (no leading v).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../..');
let version = process.env.RELEASE_VERSION?.trim() ?? '';
if (version.startsWith('v')) {
    version = version.slice(1);
}

if (!version || !/^\d+\.\d+\.\d+$/.test(version)) {
    console.error('Set RELEASE_VERSION to a semver string, e.g. 1.2.3 or v1.2.3.');
    process.exit(1);
}

const aspPath = path.join(root, 'app/Providers/AppServiceProvider.php');
let asp = fs.readFileSync(aspPath, 'utf8');
asp = asp.replace(/->version\('\d+\.\d+\.\d+'/, `->version('${version}'`);
fs.writeFileSync(aspPath, asp);

const tauriPath = path.join(root, 'src-tauri/tauri.conf.json');
const tauri = JSON.parse(fs.readFileSync(tauriPath, 'utf8'));
tauri.version = version;
fs.writeFileSync(tauriPath, JSON.stringify(tauri, null, 4) + '\n');

console.log(`Release version set to ${version}`);
