// Easing + time helpers. Tout est fonction pure du temps t (déterministe).
const clamp01 = (x) => Math.min(1, Math.max(0, x));
const lin = (x) => x;
const easeOutCubic = (x) => 1 - Math.pow(1 - x, 3);
const easeInCubic = (x) => x * x * x;
const easeInOutCubic = (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2);
const easeOutBack = (x, s = 1.25) => 1 + (s + 1) * Math.pow(x - 1, 3) + s * Math.pow(x - 1, 2);

// Progression 0→1 d'un segment [start, start+dur] avec une courbe.
const seg = (t, start, dur, ease = lin) => ease(clamp01((t - start) / dur));

const mixRGB = (a, b, p) => a.map((v, i) => Math.round(v + (b[i] - v) * p));
const rgb = (c) => `rgb(${c[0]},${c[1]},${c[2]})`;

// PRNG déterministe (pour les bulles)
function mulberry32(a) {
  return function () {
    a |= 0; a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
