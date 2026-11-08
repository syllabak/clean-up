#!/usr/bin/env python3
"""Aligne le texte de la voix off sur les morceaux de parole détectés (silencedetect).

Principe : on répartit les mots dans l'ordre sur les N morceaux de parole. On garde la répartition qui
(1) donne à chaque morceau une durée proche de (nombre de syllabes x durée moyenne d'une syllabe),
(2) fait coïncider les vraies pauses avec la ponctuation du texte (point = longue pause, virgule = courte).
Sortie : début et fin estimés de chaque phrase, dans vo_timing.json.
"""
import json, re, subprocess, sys

VOICE = 'voice.mp3'
TEXT = [
    "Un canapé taché.", "Une voiture poussiéreuse.", "Et pas une minute à perdre ?",
    "Chez Clean Up, tout se réserve en trois écrans.",
    "Premier écran : vous choisissez votre service.", "Le prix s'affiche tout de suite, sans devis.",
    "Deuxième écran : votre quartier, puis l'heure qui vous arrange.", "Les créneaux libres apparaissent aussitôt.",
    "Troisième écran : votre nom, votre numéro.", "Un clic, et c'est confirmé.",
    "Vous recevez un lien de suivi par message.", "Un clic, et vous savez où en est votre réservation.",
    "Notre équipe arrive chez vous, avec son matériel.", "Et le résultat, le voilà.",
    "Clean Up.", "Réservez dès maintenant.",
]


def silences():
    out = subprocess.run(['ffmpeg', '-hide_banner', '-i', VOICE, '-af', 'silencedetect=n=-33dB:d=0.11', '-f', 'null', '-'], capture_output=True, text=True).stderr
    ev = re.findall(r'silence_(start|end): ([0-9.]+)', out)
    dur = float(subprocess.run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', VOICE], capture_output=True, text=True).stdout)
    segs, prev, st = [], 0.0, None
    for k, v in ev:
        v = float(v)
        if k == 'start': st = v
        else:
            if st is not None and st > prev + 0.05: segs.append((prev, st))
            prev = v
    if dur - prev > 0.15: segs.append((prev, dur))
    return segs


def syl(w):
    w = w.lower()
    n = len(re.findall(r'[aeiouyàâäéèêëîïôöùûü]+', w))
    if w.endswith(('e', 'es')) and n > 1 and not w.endswith(('ée', 'ées')): n -= 1   # e muet final
    return max(1, n)


words = []   # (mot, syllabes, force de la pause qui suit : 2 = fin de phrase, 1 = virgule ou deux-points, 0 = aucune)
sent_of = []
for si, s in enumerate(TEXT):
    toks = s.split()
    for wi, t in enumerate(toks):
        pause = 0
        if wi == len(toks) - 1: pause = 2
        elif t.endswith((',', ':')): pause = 1
        words.append((t, syl(re.sub(r"[^\wàâäéèêëîïôöùûü']", '', t)), pause)); sent_of.append(si)

segs = silences()
N, W = len(segs), len(words)
gap = [segs[i + 1][0] - segs[i][1] for i in range(N - 1)] + [0.0]
tot_syl = sum(w[1] for w in words)
speech = sum(b - a for a, b in segs)
K = speech / tot_syl                                   # durée moyenne d'une syllabe
cum = [0]
for w in words: cum.append(cum[-1] + w[1])
INF = 1e18
# best[j][i] : meilleur coût en ayant placé les i premiers mots dans les j premiers morceaux
best = [[INF] * (W + 1) for _ in range(N + 1)]
prev = [[-1] * (W + 1) for _ in range(N + 1)]
best[0][0] = 0.0
expect = {0: 0.12, 1: 0.24, 2: 0.40}
for j in range(1, N + 1):
    d = segs[j - 1][1] - segs[j - 1][0]
    for i in range(j, W + 1):
        for p in range(j - 1, i):
            if best[j - 1][p] >= INF: continue
            n = cum[i] - cum[p]
            c = (d - K * n) ** 2 / (K * n + 0.25)
            if j < N: c += 6 * (gap[j - 1] - expect[words[i - 1][2]]) ** 2          # la pause qui suit doit correspondre à la ponctuation
            if best[j - 1][p] + c < best[j][i]: best[j][i] = best[j - 1][p] + c; prev[j][i] = p
i, j, cuts = W, N, []
while j > 0:
    p = prev[j][i]; cuts.append((p, i, j - 1)); i, j = p, j - 1
cuts.reverse()
# début / fin de chaque phrase = premier / dernier morceau qui touche ses mots
first, last = {}, {}
for p, i2, sj in cuts:
    for wi in range(p, i2):
        s = sent_of[wi]
        first.setdefault(s, segs[sj][0] + (segs[sj][1] - segs[sj][0]) * (cum[wi] - cum[p]) / max(1, cum[i2] - cum[p]))
        last[s] = segs[sj][0] + (segs[sj][1] - segs[sj][0]) * (cum[wi + 1] - cum[p]) / max(1, cum[i2] - cum[p])
out = [{'phrase': TEXT[s], 'debut': round(first[s], 2), 'fin': round(last[s], 2)} for s in range(len(TEXT))]
json.dump({'morceaux': [[round(a, 2), round(b, 2)] for a, b in segs], 'phrases': out, 'duree': round(segs[-1][1], 2)}, open('vo_timing.json', 'w'), ensure_ascii=False, indent=1)
print('%d morceaux, %d mots, %.3f s/syllabe, coût %.2f' % (N, W, K, best[N][W]))
for o in out: print('%6.2f -> %6.2f  %s' % (o['debut'], o['fin'], o['phrase']))
