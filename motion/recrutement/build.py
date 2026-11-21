#!/usr/bin/env python3
"""Vidéo verticale de recrutement / formation (1080x1920) à partir de deux vidéos de démonstration du matériel.
Usage : python3 build.py <vidéo A.mp4> <vidéo B.mp4> <musique.mp4> [url du formulaire] [téléphone]
Les extraits choisis évitent les logos et textes du fournisseur ; les vidéos sources ne sont pas versionnées."""
import subprocess, sys, pathlib
A, B, MUSIC = sys.argv[1:4]
FORM = sys.argv[4] if len(sys.argv) > 4 else 'https://cleanup.sn/recrutement?src=video'
PHONE = sys.argv[5] if len(sys.argv) > 5 else '+221 76 126 76 38'
ROOT = pathlib.Path(__file__).parent; W = ROOT / 'work'; OUT = ROOT / 'out'; W.mkdir(exist_ok=True); OUT.mkdir(exist_ok=True)
run = lambda *a: subprocess.run(list(map(str, a)), check=True)
run('node', ROOT / 'overlays.cjs', W, FORM, PHONE)
# (source, début, durée, zone utile « largeur:hauteur:x:y », calque)
CLIPS = [(B, 6, 4.0, '1024:580:166:52', 'cap1'), (A, 7.6, 4.0, '954:540:0:0', 'cap2'), (B, 21, 4.0, '1024:580:166:52', 'cap3'),
         (A, 47.4, 3.0, '954:540:0:0', 'cap4'), (B, 12, 4.0, '1024:580:166:52', 'cap5')]
FPS = 30
def card(name, dur):
    run('ffmpeg', '-loglevel', 'error', '-y', '-loop', 1, '-framerate', FPS, '-t', dur, '-i', W / f'{name}.png', '-vf', f'fade=t=in:st=0:d=0.3,fade=t=out:st={dur-0.3}:d=0.3,format=yuv420p', '-r', FPS, '-c:v', 'libx264', '-crf', 17, W / f'{name}.mp4')
card('title', 3.0); card('end', 5.5)
parts = [W / 'title.mp4']
for i, (src, t, d, crop, cap) in enumerate(CLIPS):
    out = W / f'clip{i}.mp4'
    fc = (f"[0:v]crop={crop},split[a][b];[a]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,boxblur=40:6,eq=brightness=-0.25[bg];"
          f"[b]scale=1080:-2[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2+40[v];[v][1:v]overlay=0:0:format=auto,fade=t=in:st=0:d=0.25,fade=t=out:st={d-0.25}:d=0.25,format=yuv420p[o]")
    run('ffmpeg', '-loglevel', 'error', '-y', '-ss', t, '-t', d, '-i', src, '-loop', 1, '-framerate', FPS, '-t', d, '-i', W / f'{cap}.png', '-filter_complex', fc, '-map', '[o]', '-an', '-r', FPS, '-c:v', 'libx264', '-crf', 17, out)
    parts.append(out)
parts.append(W / 'end.mp4')
(W / 'list.txt').write_text(''.join(f"file '{p}'\n" for p in parts))
run('ffmpeg', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', 0, '-i', W / 'list.txt', '-c', 'copy', W / 'muet.mp4')
dur = 3.0 + sum(c[2] for c in CLIPS) + 5.5
run('ffmpeg', '-loglevel', 'error', '-y', '-i', W / 'muet.mp4', '-i', MUSIC, '-filter_complex',
    f"[1:a]aformat=channel_layouts=stereo,atrim=0:{dur},asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.8,afade=t=out:st={dur-2.5}:d=2.5,loudnorm=I=-15:TP=-1.5:LRA=9[a]",
    '-map', '0:v', '-map', '[a]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-t', dur, '-movflags', '+faststart', OUT / 'cleanup-recrutement.mp4')
print('OK ->', OUT / 'cleanup-recrutement.mp4', f'({dur:.1f} s)')
