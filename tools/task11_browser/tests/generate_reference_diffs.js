const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = process.argv[2];
if (!root) throw new Error('usage: node generate_reference_diffs.js <output-dir>');

// Masks are limited to dynamic media/person/name pixels. They never cover
// layout, controls, typography, or other UI geometry.
const masks = {
  chat: [{ name: 'provider-avatar', x: 18, y: 18, width: 80, height: 80 }],
  video: [
    { name: 'remote-media-core', x: 32, y: 360, width: 642, height: 690 },
    { name: 'remote-media-lower', x: 32, y: 1060, width: 430, height: 220 },
    { name: 'self-preview-media', x: 495, y: 1090, width: 145, height: 130 },
  ],
  voice: [{ name: 'provider-avatar', x: 230, y: 480, width: 250, height: 250 }],
};

function pngSize(file) {
  const b = fs.readFileSync(file);
  if (b.toString('ascii', 1, 4) !== 'PNG') throw new Error(`not PNG: ${file}`);
  return { width: b.readUInt32BE(16), height: b.readUInt32BE(20) };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 706, height: 1600 } });
  const availableModes = ['chat', 'video', 'voice'].filter(mode => {
    const dir = path.join(root, mode);
    return fs.existsSync(path.join(dir, 'reference.png')) && fs.existsSync(path.join(dir, 'actual.png'));
  });
  if (availableModes.length === 0) throw new Error('No reference/actual mode artifacts found');
  for (const mode of availableModes) {
    const dir = path.join(root, mode);
    const referenceFile = path.join(dir, 'reference.png');
    const actualFile = path.join(dir, 'actual.png');
    const referenceSize = pngSize(referenceFile);
    const actualSize = pngSize(actualFile);
    if (referenceSize.width !== actualSize.width || referenceSize.height !== actualSize.height) {
      throw new Error(`${mode}: reference/actual canvas dimensions differ (${referenceSize.width}x${referenceSize.height} vs ${actualSize.width}x${actualSize.height})`);
    }
    const reference = `data:image/png;base64,${fs.readFileSync(referenceFile, 'base64')}`;
    const actual = `data:image/png;base64,${fs.readFileSync(actualFile, 'base64')}`;
    await page.setContent(`<canvas id="overlay"></canvas><canvas id="difference"></canvas><canvas id="mask"></canvas><img id="ref" src="${reference}"><img id="act" src="${actual}">`);
    const outputs = await page.evaluate(async ({ width, height, regions }) => {
      const ref = document.getElementById('ref');
      const act = document.getElementById('act');
      await Promise.all([ref.decode(), act.decode()]);
      if (ref.naturalWidth !== width || ref.naturalHeight !== height || act.naturalWidth !== width || act.naturalHeight !== height) {
        throw new Error('decoded image dimensions differ from PNG contract');
      }
      const overlay = document.getElementById('overlay');
      const diff = document.getElementById('difference');
      const mask = document.getElementById('mask');
      for (const c of [overlay, diff, mask]) { c.width = width; c.height = height; }
      const oc = overlay.getContext('2d');
      oc.globalAlpha = 0.5; oc.drawImage(ref, 0, 0); oc.globalAlpha = 0.5; oc.drawImage(act, 0, 0); oc.globalAlpha = 1;
      const rc = document.createElement('canvas'); rc.width = width; rc.height = height;
      const ac = document.createElement('canvas'); ac.width = width; ac.height = height;
      const rr = rc.getContext('2d'); const ar = ac.getContext('2d');
      rr.drawImage(ref, 0, 0); ar.drawImage(act, 0, 0);
      const rd = rr.getImageData(0, 0, width, height).data;
      const ad = ar.getImageData(0, 0, width, height).data;
      const mc = mask.getContext('2d');
      mc.fillStyle = '#000'; mc.fillRect(0, 0, width, height);
      mc.fillStyle = '#fff';
      for (const region of regions) mc.fillRect(region.x, region.y, region.width, region.height);
      const md = mc.getImageData(0, 0, width, height).data;
      const dc = diff.getContext('2d'); const out = dc.createImageData(width, height);
      for (let i = 0; i < out.data.length; i += 4) {
        if (md[i] > 0) { out.data[i] = out.data[i + 1] = out.data[i + 2] = 0; out.data[i + 3] = 255; continue; }
        out.data[i] = Math.abs(rd[i] - ad[i]);
        out.data[i + 1] = Math.abs(rd[i + 1] - ad[i + 1]);
        out.data[i + 2] = Math.abs(rd[i + 2] - ad[i + 2]);
        out.data[i + 3] = 255;
      }
      dc.putImageData(out, 0, 0);
      return { overlay: overlay.toDataURL('image/png'), difference: diff.toDataURL('image/png'), mask: mask.toDataURL('image/png') };
    }, { width: referenceSize.width, height: referenceSize.height, regions: masks[mode] });
    for (const [name, data] of Object.entries(outputs)) fs.writeFileSync(path.join(dir, `${name}.png`), Buffer.from(data.split(',')[1], 'base64'));
    fs.writeFileSync(path.join(dir, 'mask.json'), JSON.stringify({ mode, canvas: referenceSize, ui_geometry_masked: false, documented_dynamic_regions: masks[mode] }, null, 2));
  }
  await browser.close();
})().catch(error => { console.error(error.stack || error); process.exitCode = 1; });
