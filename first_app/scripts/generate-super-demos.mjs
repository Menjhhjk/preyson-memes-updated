// Optional asset authoring tool: npm install --prefix storage/framework/asset-tools gifenc
// Then: node scripts/generate-super-demos.mjs
// The generated files are committed; running the website needs no GIF encoder.
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
const require = createRequire(import.meta.url);
const { GIFEncoder } = require(
    resolve(
        process.argv[2] || 'storage/framework/asset-tools/node_modules/gifenc',
    ),
);
const out = resolve('public/super-reactions/demo');
mkdirSync(out, { recursive: true });
const size = 256;
const palette = [
    [40, 23, 59],
    [72, 40, 104],
    [118, 64, 173],
    [167, 117, 215],
    [255, 217, 106],
    [255, 243, 193],
    [255, 123, 168],
    [255, 213, 229],
    [139, 221, 220],
    [255, 255, 255],
];
function polygon(pixels, points, color) {
    const minY = Math.max(0, Math.floor(Math.min(...points.map((p) => p[1]))));
    const maxY = Math.min(
        size - 1,
        Math.ceil(Math.max(...points.map((p) => p[1]))),
    );
    for (let y = minY; y <= maxY; y++) {
        const intersections = [];
        for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
            const [xi, yi] = points[i],
                [xj, yj] = points[j];
            if (yi > y !== yj > y)
                intersections.push(xi + ((y - yi) * (xj - xi)) / (yj - yi));
        }
        intersections.sort((a, b) => a - b);
        for (let i = 0; i + 1 < intersections.length; i += 2) {
            for (
                let x = Math.max(0, Math.ceil(intersections[i]));
                x <= Math.min(size - 1, intersections[i + 1]);
                x++
            )
                pixels[y * size + x] = color;
        }
    }
}
function star(cx, cy, radius, angle = 0) {
    return Array.from({ length: 10 }, (_, i) => {
        const a = angle + (i * Math.PI) / 5 - Math.PI / 2,
            r = i % 2 ? radius * 0.46 : radius;
        return [cx + Math.cos(a) * r, cy + Math.sin(a) * r];
    });
}
for (const name of ['stellar', 'hype', 'love']) {
    const gif = GIFEncoder();
    for (let frame = 0; frame < 30; frame++) {
        const pixels = new Uint8Array(size * size),
            t = (frame / 30) * Math.PI * 2;
        for (let y = 0; y < size; y++)
            for (let x = 0; x < size; x++) {
                const distance = Math.hypot(x - 128, y - 128);
                pixels[y * size + x] = distance < 102 + Math.sin(t) * 5 ? 1 : 0;
            }
        for (let i = 0; i < 9; i++) {
            const a = (i * Math.PI * 2) / 9 + t / 18,
                r = 100 + Math.sin(t + i) * 8;
            polygon(
                pixels,
                star(
                    128 + Math.cos(a) * r,
                    128 + Math.sin(a) * r,
                    4 + 2 * Math.sin(t + i),
                    t,
                ),
                i % 3 ? 4 : 8,
            );
        }
        if (name === 'stellar') {
            polygon(
                pixels,
                star(128, 128 + Math.sin(t) * 5, 72, Math.sin(t) * 0.08),
                4,
            );
            polygon(
                pixels,
                star(128, 128 + Math.sin(t) * 5, 56, Math.sin(t) * 0.08),
                5,
            );
            polygon(
                pixels,
                [
                    [106, 117],
                    [113, 117],
                    [113, 130],
                    [106, 130],
                ],
                0,
            );
            polygon(
                pixels,
                [
                    [143, 117],
                    [150, 117],
                    [150, 130],
                    [143, 130],
                ],
                0,
            );
            polygon(
                pixels,
                [
                    [115, 141],
                    [141, 141],
                    [133, 151],
                    [123, 151],
                ],
                0,
            );
        } else if (name === 'love') {
            const heart = Array.from({ length: 100 }, (_, i) => {
                const a = (i / 100) * Math.PI * 2,
                    scale = 4.4 + Math.sin(t) * 0.18;
                return [
                    128 + 16 * Math.sin(a) ** 3 * scale,
                    123 -
                        (13 * Math.cos(a) -
                            5 * Math.cos(2 * a) -
                            2 * Math.cos(3 * a) -
                            Math.cos(4 * a)) *
                            scale,
                ];
            });
            polygon(
                pixels,
                heart.map(([x, y]) => [x + 3, y + 7]),
                2,
            );
            polygon(pixels, heart, 6);
            polygon(pixels, star(95, 102, 12, t * 0.15), 7);
        } else {
            const offset = Math.sin(t) * 6;
            polygon(
                pixels,
                [
                    [143, 51 + offset],
                    [86, 133 + offset],
                    [120, 133 + offset],
                    [103, 204 + offset],
                    [179, 109 + offset],
                    [142, 109 + offset],
                    [164, 51 + offset],
                ],
                4,
            );
            polygon(
                pixels,
                [
                    [141, 66 + offset],
                    [104, 124 + offset],
                    [130, 124 + offset],
                    [120, 171 + offset],
                    [160, 121 + offset],
                    [131, 121 + offset],
                    [148, 66 + offset],
                ],
                5,
            );
        }
        gif.writeFrame(pixels, size, size, { palette, delay: 70, repeat: 0 });
    }
    gif.finish();
    writeFileSync(`${out}/${name}.gif`, gif.bytes());
}
// Quiet, original synthesized sounds; no external media or licensed samples.
for (const [name, notes] of Object.entries({
    stellar: [523.25, 659.25, 783.99],
    hype: [392, 523.25, 783.99],
    love: [440, 554.37, 659.25],
    boost: [220, 440, 880],
})) {
    const rate = 22050,
        seconds = 1.6,
        length = Math.floor(rate * seconds),
        data = Buffer.alloc(44 + length * 2);
    data.write('RIFF', 0);
    data.writeUInt32LE(data.length - 8, 4);
    data.write('WAVEfmt ', 8);
    data.writeUInt32LE(16, 16);
    data.writeUInt16LE(1, 20);
    data.writeUInt16LE(1, 22);
    data.writeUInt32LE(rate, 24);
    data.writeUInt32LE(rate * 2, 28);
    data.writeUInt16LE(2, 32);
    data.writeUInt16LE(16, 34);
    data.write('data', 36);
    data.writeUInt32LE(length * 2, 40);
    for (let i = 0; i < length; i++) {
        const t = i / rate;
        let v = 0;
        for (let n = 0; n < notes.length; n++) {
            const d = t - n * 0.16;
            if (d >= 0)
                v +=
                    (Math.sin(2 * Math.PI * notes[n] * d) *
                        Math.min(1, d / 0.02) *
                        Math.exp(-d * 3)) /
                    3;
        }
        if (name === 'boost')
            v +=
                Math.sin(2 * Math.PI * (100 * t + 230 * t * t)) *
                Math.sin((Math.PI * t) / seconds) *
                0.2;
        v *= Math.min(1, (seconds - t) * 20);
        data.writeInt16LE(Math.round(v * 8000), 44 + i * 2);
    }
    writeFileSync(`${out}/${name}.wav`, data);
}
console.log('Generated 3 animated GIFs and 4 demo sounds.');
