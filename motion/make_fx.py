"""Piste d'effets sonores synthétisée (aucune ressource externe), calée sur tokens.js.
Usage : python3 motion/make_fx.py  ->  motion/out/cleanup-fx.wav (stéréo 44,1 kHz)"""
import numpy as np, wave, pathlib
SR, DUR = 44100, 16.0
rng = np.random.default_rng(3)
out = np.zeros((int(SR * DUR), 2))

def env(n, a, r):
    e = np.ones(n); na, nr = int(a * SR), int(r * SR)
    if na: e[:na] = np.linspace(0, 1, na)
    if nr: e[-nr:] = np.linspace(1, 0, nr) ** 2
    return e

def put(t, sig, gain=1.0, pan=0.0):
    i = int(t * SR); sig = sig[: len(out) - i]
    out[i:i + len(sig), 0] += sig * gain * (1 - max(pan, 0))
    out[i:i + len(sig), 1] += sig * gain * (1 + min(pan, 0))

def lowpass(x, k):  # moyenne glissante simple
    return np.convolve(x, np.ones(k) / k, mode='same')

def whoosh(d=0.7, bright=0.5):
    n = int(d * SR); x = rng.standard_normal(n)
    sweep = np.linspace(40, 6, n)  # filtre qui s'ouvre puis se ferme
    y = np.array([0.0]); a = 1 / (1 + sweep * (1 - bright) * 3)
    y = np.zeros(n); s = 0.0
    for i in range(n): s += a[i] * (x[i] - s); y[i] = s
    e = np.sin(np.linspace(0, np.pi, n)) ** 1.5
    return y * e * 2.2

def tone(f, d, decay=8, harm=(1,), bend=0.0):
    n = int(d * SR); t = np.arange(n) / SR
    ph = 2 * np.pi * (f * t + bend * t * t / 2)
    y = sum(np.sin(h * ph) / h for h in harm) * np.exp(-decay * t)
    return y * env(n, 0.002, 0.01)

def pop(f=520): return tone(f, 0.22, 16, (1, 2), bend=-f * 0.8) * 0.9
def click(): return tone(1500, 0.05, 70, (1, 3)) * 0.8
def thud(): return tone(95, 0.22, 20, (1, 2)) * 0.8
def chime():
    return sum(tone(f, 1.1, 4.5, (1, 2)) * g for f, g in ((880, 1), (1318.5, .8), (1760, .6)))

# --- Beats (voir tokens.js) ---
for i, t in enumerate((0.15, 0.43, 0.71)): put(t, thud(), 0.35)              # lignes « sales »
put(1.35, whoosh(0.8, .6), 0.55, -0.2); put(1.35, tone(2400, .12, 25) * .25) # raclette
put(2.55, whoosh(0.8, .3), 0.5)                                              # cercle marine
put(3.05, pop(480), 0.7); put(3.12, tone(1100, .3, 9, (1, 2)) * .25)         # logo
for i in range(3): put(4.95 + i * .28, pop(620 + i * 70), 0.45, (i - 1) * .3) # cartes
put(9.15, whoosh(.7, .5), .45)                                               # téléphone
for t in (10.35, 11.15, 11.95): put(t, click(), .6)                          # sélections
put(12.75, chime(), .5)                                                      # confirmation
put(13.0, whoosh(.9, .2), .5)                                                # cercle clair
put(14.8, tone(1568, .5, 7, (1, 2)) * .5, .5)                                # bouton

# --- Nappe discrète (Do majeur), tremolo lent ---
t = np.arange(len(out)) / SR
pad = sum(np.sin(2 * np.pi * f * t) * g for f, g in ((130.8, 1), (196, .7), (261.6, .5), (329.6, .35)))
pad *= (0.8 + 0.2 * np.sin(2 * np.pi * 0.25 * t)) * np.minimum(1, t / 2) * np.minimum(1, (DUR - t) / 1.5)
out += pad[:, None] * 0.05

out /= max(1.0, np.abs(out).max() / 0.89)  # pas d'écrêtage
path = pathlib.Path(__file__).parent / 'out' / 'cleanup-fx.wav'
with wave.open(str(path), 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((out * 32767).astype('<i2').tobytes())
print('OK ->', path)
