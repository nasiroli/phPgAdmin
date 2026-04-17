/**
 * Copies first-class desktop bundle files into build/desktop/{version}.{ext}
 * (mirrors NativeBlade BuildCommand::searchAndCopyArtifacts for common extensions).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../..');
const tauriPath = path.join(root, 'src-tauri/tauri.conf.json');
const tauri = JSON.parse(fs.readFileSync(tauriPath, 'utf8'));
const ver = tauri.version;
const bundleRoot = path.join(root, 'src-tauri/target/release/bundle');
const outDir = path.join(root, 'build/desktop');

/** Lowercase keys; output uses NativeBlade-style casing for AppImage. */
const allowedLower = new Set(['msi', 'exe', 'dmg', 'app', 'appimage', 'deb', 'rpm']);

function destExtension(rawExt) {
    const lower = rawExt.toLowerCase();
    if (lower === 'appimage') {
        return 'AppImage';
    }

    return lower;
}

function walk(dir) {
    if (!fs.existsSync(dir)) {
        return;
    }
    for (const name of fs.readdirSync(dir)) {
        const p = path.join(dir, name);
        const st = fs.statSync(p);
        if (st.isDirectory()) {
            walk(p);
            continue;
        }
        if (!st.isFile()) {
            continue;
        }
        const rawExt = path.extname(name).slice(1);
        if (!allowedLower.has(rawExt.toLowerCase())) {
            continue;
        }
        const destExt = destExtension(rawExt);
        const dest = path.join(outDir, `${ver}.${destExt}`);
        fs.mkdirSync(outDir, { recursive: true });
        fs.copyFileSync(p, dest);
        console.log(`Copied ${path.relative(root, p)} -> ${path.relative(root, dest)}`);
    }
}

walk(bundleRoot);
